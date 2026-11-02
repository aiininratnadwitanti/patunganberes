<?php

namespace Tests\Feature;

use App\Models\BillParticipant;
use App\Models\ReceiptItem;
use App\Models\SplitBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SplitBillSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocation_step_stores_the_payer_for_each_item(): void
    {
        [$user, $bill, $participants, $items] = $this->makeBill();

        $this->actingAs($user)
            ->put(route('split-bills.allocation.update', $bill), [
                'allocations' => json_encode([
                    $items[0]->id => [
                        'type' => 'shared_equal',
                        'shared_participant_ids' => $participants->pluck('id')->all(),
                        'payer_participant_id' => $participants[1]->id,
                    ],
                ]),
            ])
            ->assertRedirect(route('split-bills.summary', $bill));

        $allocation = $bill->allocations()->where('receipt_item_id', $items[0]->id)->first();

        $this->assertNotNull($allocation);
        $this->assertSame($participants[1]->id, $allocation->payer_participant_id);
    }

    public function test_summary_shows_who_transfers_to_whom(): void
    {
        [$user, $bill] = $this->makeBill();

        $this->actingAs($user)
            ->get(route('split-bills.summary', $bill))
            ->assertOk()
            ->assertSee('Rincian pembayaran')
            ->assertSee('Kurang Rp7.000')
            ->assertSee('Kembali Rp5.000')
            ->assertSee('Rp5.000');
    }

    public function test_guest_receipt_tells_the_participant_where_to_transfer(): void
    {
        [$user, $bill, $participants] = $this->makeBill();

        $debtor = $participants[2];

        $this->get(route('guest.receipt', [$bill->code, $debtor->public_token]))
            ->assertOk()
            ->assertSee('Transfer ke')
            ->assertSee('Kurang Rp7.000')
            ->assertSee('Budi')
            ->assertSee('Ainin');
    }

    /**
     * @return array{0: User, 1: SplitBill, 2: Collection<int, BillParticipant>, 3: Collection<int, ReceiptItem>}
     */
    private function makeBill(): array
    {
        $user = User::factory()->create();

        $bill = $user->splitBills()->create([
            'title' => 'Makan Bareng',
            'merchant_name' => 'Warung Nusantara',
            'date' => now()->toDateString(),
            'subtotal' => 30000,
            'grand_total' => 30000,
            'rounding_method' => 'none',
            'status' => 'draft',
        ]);

        $participants = collect([
            ['name' => 'Ainin', 'is_current_user' => true, 'payment_status' => 'paid'],
            ['name' => 'Budi'],
            ['name' => 'Citra'],
        ])->map(fn (array $attributes, int $index) => $bill->participants()->create(
            $attributes + ['sort_order' => $index, 'color' => participant_color($index)]
        ));

        $items = collect([
            ['Makan', 12000, 0],
            ['Minum', 15000, 1],
            ['Jajan', 3000, 2],
        ])->map(fn (array $row, int $index) => $bill->items()->create([
            'name' => $row[0],
            'quantity' => 1,
            'unit_price' => $row[1],
            'total_price' => $row[1],
            'sort_order' => $index,
        ]));

        // Each person fronts one item; everything is split three ways.
        $items->each(function (ReceiptItem $item, int $index) use ($bill, $participants) {
            $bill->allocations()->create([
                'receipt_item_id' => $item->id,
                'type' => 'shared_equal',
                'shared_participant_ids' => $participants->pluck('id')->all(),
                'payer_participant_id' => $participants[$index]->id,
            ]);
        });

        return [$user, $bill, $participants, $items];
    }
}
