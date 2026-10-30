<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IuranTest extends TestCase
{
    use RefreshDatabase;

    private function makePool(User $user, array $overrides = [])
    {
        return $user->iuranPools()->create(array_merge([
            'code' => 'IRN-2026-TEST',
            'title' => 'Kas Kelas',
            'target_amount' => 100000,
            'fee_per_person' => 25000,
            'deadline' => now()->addDays(7)->toDateString(),
            'category' => 'kas_kelas',
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => $user->name,
        ], $overrides));
    }

    public function test_creating_an_iuran_pool_starts_with_nothing_collected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('iuran.store'), [
            'title' => 'Kas Kelas November',
            'description' => 'Untuk fotokopi modul',
            'target_amount' => 100000,
            'fee_per_person' => 25000,
            'pricing_type' => 'fixed',
            'deadline' => now()->addDays(7)->toDateString(),
            'category' => 'kas_kelas',
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => $user->name,
        ])->assertRedirect();

        $pool = $user->iuranPools()->firstOrFail();

        $this->assertSame(0, $pool->participants()->count());
        $this->assertSame(0, $pool->current_collected);
        $this->assertSame('active', $pool->status);
        $this->assertNotEmpty($pool->public_token);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'related_type' => 'iuran',
            'related_id' => $pool->code,
        ]);
    }

    public function test_a_guest_contribution_reduces_the_remaining_target_immediately(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $pool = $this->makePool($user);

        $this->post(route('guest.iuran.report', [$pool->code, $pool->public_token]), [
            'name' => 'Ani',
            'amount' => 25000,
            'notes' => 'Transfer BCA',
            'proof' => UploadedFile::fake()->create('bukti.jpg', 200, 'image/jpeg'),
        ])->assertRedirect(route('guest.iuran', [$pool->code, $pool->public_token]));

        $participant = $pool->participants()->firstOrFail();

        $this->assertSame('Ani', $participant->name);
        $this->assertSame('waiting_verification', $participant->status);
        $this->assertSame(25000, $participant->paid_amount);
        $this->assertNotEmpty($participant->proof_url);

        // Nominal langsung memotong sisa target, tanpa menunggu verifikasi.
        $this->assertSame(25000, $pool->fresh()->current_collected);
    }

    public function test_a_contribution_can_exceed_the_target(): void
    {
        $user = User::factory()->create();
        $pool = $this->makePool($user, ['target_amount' => 10000, 'fee_per_person' => 10000]);

        $this->post(route('guest.iuran.report', [$pool->code, $pool->public_token]), [
            'name' => 'Ani',
            'amount' => 25000,
        ])->assertRedirect();

        $pool->refresh();
        $this->assertSame(25000, $pool->current_collected);
        $this->assertSame('completed', $pool->status);

        // Halaman pembuat tetap tampil walau total melebihi target.
        $this->actingAs($user)
            ->get(route('iuran.index', ['pool' => $pool->id]))
            ->assertOk()
            ->assertSee('Melebihi target');
    }

    public function test_iuran_pages_render_with_a_contribution(): void
    {
        $user = User::factory()->create();
        $pool = $this->makePool($user);
        $pool->participants()->create(['name' => 'Ani', 'paid_amount' => 25000, 'status' => 'waiting_verification']);
        $pool->refreshCollected();

        $this->actingAs($user)
            ->get(route('iuran.index', ['pool' => $pool->id]))
            ->assertOk();

        $this->get(route('guest.iuran', [$pool->code, $pool->public_token]))->assertOk();
    }

    public function test_owner_verifies_a_contribution(): void
    {
        $user = User::factory()->create();
        $pool = $this->makePool($user);
        $participant = $pool->participants()->create(['name' => 'Ani', 'paid_amount' => 25000, 'status' => 'waiting_verification']);
        $pool->refreshCollected();

        $this->actingAs($user)
            ->patch(route('iuran.participant.update', $pool), [
                'participant_id' => $participant->id,
                'status' => 'paid',
            ])->assertRedirect();

        $this->assertSame('paid', $participant->fresh()->status);
        $this->assertSame(25000, $pool->fresh()->current_collected);
    }

    public function test_owner_can_cancel_a_verification(): void
    {
        $user = User::factory()->create();
        $pool = $this->makePool($user);
        $participant = $pool->participants()->create(['name' => 'Ani', 'paid_amount' => 25000, 'status' => 'paid', 'paid_at' => now()]);
        $pool->refreshCollected();

        $this->actingAs($user)
            ->patch(route('iuran.participant.update', $pool), [
                'participant_id' => $participant->id,
                'status' => 'waiting_verification',
            ])->assertRedirect();

        $this->assertSame('waiting_verification', $participant->fresh()->status);
        // Nominal tetap dihitung karena kini menunggu verifikasi lagi.
        $this->assertSame(25000, $pool->fresh()->current_collected);
    }

    public function test_owner_rejecting_a_contribution_removes_it_from_the_total(): void
    {
        $user = User::factory()->create();
        $pool = $this->makePool($user);
        $participant = $pool->participants()->create(['name' => 'Ani', 'paid_amount' => 25000, 'status' => 'waiting_verification']);
        $pool->refreshCollected();
        $this->assertSame(25000, $pool->current_collected);

        $this->actingAs($user)
            ->patch(route('iuran.participant.update', $pool), [
                'participant_id' => $participant->id,
                'status' => 'rejected',
            ])->assertRedirect();

        $this->assertSame('rejected', $participant->fresh()->status);
        $this->assertSame(0, $pool->fresh()->current_collected);
    }

    public function test_owner_can_delete_an_iuran_pool(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $pool = $this->makePool($user, ['code' => 'IRN-X-2']);
        $pool->participants()->create(['name' => 'Ani', 'paid_amount' => 10000, 'status' => 'paid']);

        $this->delete(route('iuran.destroy', $pool))->assertRedirect(route('iuran.index'));

        $this->assertDatabaseMissing('iuran_pools', ['id' => $pool->id]);
        $this->assertDatabaseMissing('iuran_participants', ['iuran_pool_id' => $pool->id]);
    }

    public function test_a_user_cannot_delete_another_users_iuran_pool(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $pool = $this->makePool($owner, ['code' => 'IRN-X-3', 'target_amount' => 10000]);

        $this->actingAs($other)
            ->delete(route('iuran.destroy', $pool))
            ->assertForbidden();

        $this->assertDatabaseHas('iuran_pools', ['id' => $pool->id]);
    }

    public function test_iuran_pools_are_isolated_per_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $pool = $this->makePool($owner, ['code' => 'IRN-X-1']);
        $participant = $pool->participants()->create(['name' => 'Ani', 'paid_amount' => 25000, 'status' => 'waiting_verification']);

        $this->actingAs($other)
            ->patch(route('iuran.participant.update', $pool), [
                'participant_id' => $participant->id,
                'status' => 'paid',
            ])
            ->assertForbidden();
    }
}
