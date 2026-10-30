<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SplitBillFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_mode_creates_a_draft_bill(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/split-bills', ['mode' => 'manual'])
            ->assertRedirect();

        $bill = $user->splitBills()->first();

        $this->assertNotNull($bill);
        $this->assertSame('draft', $bill->status);
        $this->assertGreaterThanOrEqual(1, $bill->items()->count());
        $this->assertGreaterThanOrEqual(1, $bill->participants()->count());
    }

    public function test_a_user_cannot_view_another_users_bill(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $bill = $owner->splitBills()->create([
            'title' => 'Makan Siang',
            'merchant_name' => 'Kedai Nusantara',
            'date' => now()->toDateString(),
        ]);

        $this->actingAs($other)
            ->get(route('split-bills.show', $bill))
            ->assertForbidden();
    }

    public function test_history_page_lists_the_users_bills(): void
    {
        $user = User::factory()->create();

        $user->splitBills()->create([
            'title' => 'Nongkrong Kopi Senja',
            'merchant_name' => 'Kopi Senja',
            'date' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get('/split-bills')
            ->assertOk()
            ->assertSee('Nongkrong Kopi Senja');
    }
}
