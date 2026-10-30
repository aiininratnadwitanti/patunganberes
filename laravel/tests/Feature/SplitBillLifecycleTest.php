<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SplitBillLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_full_split_bill_lifecycle_works(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 1. Create a blank bill (manual mode).
        $this->post('/split-bills', ['mode' => 'manual'])->assertRedirect();
        $bill = $user->splitBills()->latest('id')->firstOrFail();

        // 2. Fill in receipt details.
        $this->put(route('split-bills.validate.update', $bill), [
            'merchant_name' => 'Warung Test',
            'date' => now()->toDateString(),
            'time' => '12:00',
            'category' => 'restoran',
            'tax_amount' => 10000,
            'service_charge_amount' => 5000,
            'discount_amount' => 0,
            'other_fees_amount' => 0,
            'items' => [
                ['name' => 'Nasi Goreng', 'quantity' => 2, 'unit_price' => 25000],
            ],
        ])->assertRedirect(route('split-bills.participants', $bill));

        $bill->refresh();
        $this->assertSame(50000, $bill->subtotal);
        $this->assertSame(65000, $bill->grand_total);

        // 3. Trim the participant list down to two people.
        $participants = $bill->participants()->get();
        [$owner, $friend] = [$participants[0], $participants[1]];

        $this->put(route('split-bills.participants.update', $bill), [
            'title' => 'Makan Siang Tim',
            'date' => now()->toDateString(),
            'participants' => [
                ['id' => $owner->id, 'name' => $owner->name, 'is_current_user' => 1],
                ['id' => $friend->id, 'name' => 'Rizka Amanda', 'is_current_user' => 0],
            ],
        ])->assertRedirect(route('split-bills.allocation', $bill));

        $bill->refresh();
        $this->assertSame(2, $bill->participants()->count());

        // 4. Allocate the single item to the owner.
        $item = $bill->items()->firstOrFail();
        $this->put(route('split-bills.allocation.update', $bill), [
            'allocations' => json_encode([
                $item->id => [
                    'type' => 'single',
                    'single_participant_id' => $owner->id,
                    'shared_participant_ids' => [],
                    'portion_assignments' => [],
                ],
            ]),
        ])->assertRedirect(route('split-bills.summary', $bill));

        // 5. Summary renders and totals add up.
        $this->get(route('split-bills.summary', $bill))->assertOk();

        // 6. Confirm the split -> generates shares.
        $this->post(route('split-bills.confirm', $bill))
            ->assertRedirect(route('split-bills.receipts', $bill));

        $bill->refresh();
        $this->assertSame('pending_payment', $bill->status);
        $this->assertSame(2, $bill->shares()->count());
        $this->assertSame($bill->grand_total, (int) $bill->shares()->sum('final_total'));

        // 7. The receipts page renders.
        $this->get(route('split-bills.receipts', $bill))->assertOk();

        // 8. The friend opens the guest receipt and reports a payment.
        $this->get(route('guest.receipt', [$bill->code, $friend->public_token]))->assertOk();

        $this->post(route('guest.receipt.report', [$bill->code, $friend->public_token]), [
            'payment_method' => 'BCA',
            'notes' => 'Transfer via BCA Mobile',
        ])->assertRedirect();

        $friend->refresh();
        $this->assertSame('waiting_verification', $friend->payment_status);

        // 9. The owner verifies the payment -> bill becomes completed.
        $this->patch(route('split-bills.payment.update', $bill), [
            'participant_id' => $friend->id,
            'payment_status' => 'paid',
        ])->assertRedirect();

        $friend->refresh();
        $bill->refresh();
        $this->assertSame('paid', $friend->payment_status);
        $this->assertSame('completed', $bill->status);

        // 10. Duplicate, archive, delete.
        $this->post(route('split-bills.duplicate', $bill))->assertRedirect();
        $copy = $user->splitBills()->latest('id')->first();
        $this->assertNotSame($bill->id, $copy->id);
        $this->assertSame('draft', $copy->status);

        $this->patch(route('split-bills.archive', $bill))->assertRedirect();
        $this->assertSame('archived', $bill->fresh()->status);

        $this->delete(route('split-bills.destroy', $copy))->assertRedirect(route('history.index'));
        $this->assertDatabaseMissing('split_bills', ['id' => $copy->id]);
    }

    public function test_a_bill_cannot_be_confirmed_while_items_are_unallocated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/split-bills', ['mode' => 'manual'])->assertRedirect();
        $bill = $user->splitBills()->latest('id')->firstOrFail();

        $this->post(route('split-bills.confirm', $bill))->assertRedirect();
        $this->assertSame('draft', $bill->fresh()->status);
    }
}
