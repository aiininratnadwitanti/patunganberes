<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    private function userWithData(): array
    {
        $user = User::factory()->create();

        $bill = $user->splitBills()->create([
            'title' => 'Makan Siang Tim',
            'merchant_name' => 'Kedai Nusantara',
            'date' => now()->toDateString(),
        ]);

        $bill->items()->create([
            'name' => 'Nasi Goreng',
            'quantity' => 2,
            'unit_price' => 25000,
            'total_price' => 50000,
        ]);

        $participant = $bill->participants()->create(['name' => 'Tamu Undangan']);

        $pool = $user->iuranPools()->create([
            'code' => 'IRN-TEST-001',
            'title' => 'Kas Kelas',
            'target_amount' => 500000,
            'fee_per_person' => 50000,
            'deadline' => now()->addWeek()->toDateString(),
            'category' => 'kas_kelas',
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => $user->name,
        ]);

        $user->appNotifications()->create([
            'title' => 'Test',
            'message' => 'Pesan',
            'type' => 'bill_created',
        ]);

        return [$user, $bill, $participant, $pool];
    }

    public function test_all_main_pages_render(): void
    {
        [$user, $bill, $participant] = $this->userWithData();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/split-bills')->assertOk();
        $this->actingAs($user)->get('/split-bills/create')->assertOk();
        $this->actingAs($user)->get('/iuran')->assertOk();
        $this->actingAs($user)->get('/notifications')->assertOk();
        $this->actingAs($user)->get('/profile')->assertOk();
        $this->actingAs($user)->get(route('split-bills.show', $bill))->assertOk();
        $this->actingAs($user)->get(route('split-bills.receipts', $bill))->assertOk();
        $this->get(route('guest.receipt', [$bill->code, $participant->public_token]))->assertOk();
    }

    public function test_split_bill_wizard_pages_render(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/split-bills', ['mode' => 'manual'])->assertRedirect();
        $bill = $user->splitBills()->latest('id')->firstOrFail();

        $this->actingAs($user)->get(route('split-bills.validate', $bill))->assertOk();
        $this->actingAs($user)->get(route('split-bills.participants', $bill))->assertOk();
        $this->actingAs($user)->get(route('split-bills.allocation', $bill))->assertOk();
        $this->actingAs($user)->get(route('split-bills.summary', $bill))->assertOk();
    }

    public function test_the_scan_form_alpine_expression_is_not_truncated(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/split-bills/create')->getContent();

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $expression = $dom->getElementsByTagName('form')->item(0)->getAttribute('x-data');

        // Kalau atribut terputus di tengah, potongan skrip ini akan bocor ke halaman.
        $this->assertStringContainsString('openCamera', $expression);
        $this->assertStringContainsString('capturePhoto', $expression);
        $this->assertStringContainsString('async submit()', $expression);
    }

    public function test_auth_pages_render(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }
}
