<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptScannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanning_without_an_api_key_fails_gracefully(): void
    {
        Storage::fake('public');
        config(['services.gemini.key' => null]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/split-bills/create')
            ->post('/split-bills', [
                'mode' => 'scanner',
                'receipt' => UploadedFile::fake()->create('struk.jpg', 200, 'image/jpeg'),
            ])
            ->assertRedirect('/split-bills/create')
            ->assertSessionHasErrors('receipt');

        $this->assertSame(0, $user->splitBills()->count());
        $this->assertCount(0, Storage::disk('public')->allFiles('receipts'));
    }

    public function test_scanning_without_a_receipt_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/split-bills/create')
            ->post('/split-bills', ['mode' => 'scanner'])
            ->assertRedirect('/split-bills/create')
            ->assertSessionHasErrors('receipt');
    }

    public function test_scanning_falls_back_to_the_next_model_when_the_primary_fails(): void
    {
        Storage::fake('public');

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'dead-model',
            'services.gemini.fallback_models' => ['good-model'],
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'dead-model')) {
                return Http::response(['error' => ['code' => 404]], 404);
            }

            return Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'merchantName' => 'Warung Test',
                        'items' => [[
                            'name' => 'Nasi Goreng',
                            'quantity' => 2,
                            'unitPrice' => 25000,
                            'totalPrice' => 50000,
                        ]],
                        'subtotal' => 50000,
                        'grandTotal' => 50000,
                    ])]]],
                ]],
            ]);
        });

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/split-bills', [
                'mode' => 'scanner',
                'receipt' => UploadedFile::fake()->create('struk.jpg', 200, 'image/jpeg'),
            ])
            ->assertRedirect();

        $bill = $user->splitBills()->latest('id')->first();

        $this->assertNotNull($bill);
        $this->assertSame('Warung Test', $bill->merchant_name);
        $this->assertSame(1, $bill->items()->count());
    }

    public function test_scanning_reports_an_error_when_every_model_fails(): void
    {
        Storage::fake('public');

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'dead-model',
            'services.gemini.fallback_models' => ['also-dead'],
        ]);

        Http::fake(fn () => Http::response(['error' => ['code' => 503]], 503));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/split-bills/create')
            ->post('/split-bills', [
                'mode' => 'scanner',
                'receipt' => UploadedFile::fake()->create('struk.jpg', 200, 'image/jpeg'),
            ])
            ->assertRedirect('/split-bills/create')
            ->assertSessionHasErrors('receipt');

        $this->assertSame(0, $user->splitBills()->count());
        $this->assertCount(0, Storage::disk('public')->allFiles('receipts'));
    }

    public function test_scanning_reports_quota_exhaustion_clearly(): void
    {
        Storage::fake('public');

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'busy-model',
            'services.gemini.fallback_models' => [],
        ]);

        Http::fake(fn () => Http::response(['error' => ['code' => 429]], 429));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/split-bills/create')
            ->post('/split-bills', [
                'mode' => 'scanner',
                'receipt' => UploadedFile::fake()->create('struk.jpg', 200, 'image/jpeg'),
            ])
            ->assertRedirect('/split-bills/create')
            ->assertSessionHasErrors([
                'receipt' => 'Kuota AI harian sudah habis. Coba lagi nanti atau gunakan input manual.',
            ]);
    }

    public function test_uploaded_images_are_compressed_before_being_sent(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension is required.');
        }

        Storage::fake('public');

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'good-model',
            'services.gemini.fallback_models' => [],
        ]);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'merchantName' => 'Toko Uji',
                        'items' => [['name' => 'Item', 'quantity' => 1, 'unitPrice' => 1000, 'totalPrice' => 1000]],
                        'grandTotal' => 1000,
                    ])]]],
                ]],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post('/split-bills', [
            'mode' => 'scanner',
            'receipt' => UploadedFile::fake()->image('struk.png', 800, 600),
        ])->assertRedirect();

        Http::assertSent(function ($request) {
            $inline = collect($request->data()['contents'][0]['parts'] ?? [])
                ->firstWhere('inline_data')['inline_data'] ?? [];

            return ($inline['mime_type'] ?? null) === 'image/jpeg';
        });
    }

    public function test_the_json_scan_endpoint_returns_a_redirect_url(): void
    {
        Storage::fake('public');

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'good-model',
            'services.gemini.fallback_models' => [],
        ]);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'merchantName' => 'Warung Test',
                        'items' => [['name' => 'Nasi Goreng', 'quantity' => 1, 'unitPrice' => 25000, 'totalPrice' => 25000]],
                        'grandTotal' => 25000,
                    ])]]],
                ]],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/split-bills/scan', [
                'receipt' => UploadedFile::fake()->create('struk.jpg', 200, 'image/jpeg'),
            ])
            ->assertOk()
            ->assertJsonStructure(['redirect']);
    }

    public function test_the_json_scan_endpoint_reports_errors_without_a_key(): void
    {
        Storage::fake('public');
        config(['services.gemini.key' => null]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/split-bills/scan', [
                'receipt' => UploadedFile::fake()->create('struk.jpg', 200, 'image/jpeg'),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error']);
    }

    public function test_scanned_date_and_time_are_normalized_for_the_database(): void
    {
        Storage::fake('public');

        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.model' => 'good-model',
            'services.gemini.fallback_models' => [],
        ]);

        // Real receipts give a time with seconds and a d/m/Y date, both of which
        // used to overflow the `time` column / fail the DATE cast.
        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'merchantName' => 'TOKO ABANG',
                        'date' => '01/01/2023',
                        'time' => '10:11:07',
                        'items' => [['name' => 'Beras', 'quantity' => 1, 'unitPrice' => 10000, 'totalPrice' => 10000]],
                        'subtotal' => 10000,
                        'grandTotal' => 10000,
                    ])]]],
                ]],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post('/split-bills', [
            'mode' => 'scanner',
            'receipt' => UploadedFile::fake()->create('struk.jpg', 200, 'image/jpeg'),
        ])->assertRedirect();

        $bill = $user->splitBills()->latest('id')->firstOrFail();

        $this->assertSame('10:11', $bill->time);
        $this->assertSame('2023-01-01', $bill->date->format('Y-m-d'));
    }
}
