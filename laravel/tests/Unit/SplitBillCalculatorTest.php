<?php

namespace Tests\Unit;

use App\Services\SplitBillCalculator;
use PHPUnit\Framework\TestCase;

class SplitBillCalculatorTest extends TestCase
{
    private SplitBillCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new SplitBillCalculator;
    }

    public function test_single_allocation_charges_the_full_item_price(): void
    {
        $result = $this->calculator->calculate([
            'items' => [
                ['id' => 1, 'name' => 'Nasi Goreng', 'quantity' => 2, 'unit_price' => 30000, 'total_price' => 60000],
            ],
            'participants' => [
                ['id' => 1, 'name' => 'Aiinin'],
                ['id' => 2, 'name' => 'Budi'],
            ],
            'allocations' => [
                ['item_id' => 1, 'type' => 'single', 'single_participant_id' => 1],
            ],
            'tax_amount' => 0,
            'service_charge_amount' => 0,
            'discount_amount' => 0,
            'other_fees_amount' => 0,
            'rounding_method' => 'none',
            'grand_total' => 60000,
        ]);

        $shares = collect($result['shares'])->keyBy('participant_id');

        $this->assertSame(60000, $shares[1]['final_total']);
        $this->assertSame(0, $shares[2]['final_total']);
        $this->assertSame([], $result['unallocated_item_ids']);
        $this->assertTrue($result['is_matched']);
    }

    public function test_shared_equal_splits_the_item_evenly(): void
    {
        $result = $this->calculator->calculate([
            'items' => [
                ['id' => 5, 'name' => 'Truffle Fries', 'quantity' => 1, 'unit_price' => 35000, 'total_price' => 35000],
            ],
            'participants' => [
                ['id' => 1, 'name' => 'A'],
                ['id' => 2, 'name' => 'B'],
            ],
            'allocations' => [
                ['item_id' => 5, 'type' => 'shared_equal', 'shared_participant_ids' => [1, 2]],
            ],
            'rounding_method' => 'none',
            'grand_total' => 35000,
        ]);

        $shares = collect($result['shares'])->keyBy('participant_id');

        $this->assertSame(17500, $shares[1]['final_total']);
        $this->assertSame(17500, $shares[2]['final_total']);
        $this->assertTrue($result['is_matched']);
    }

    public function test_proportional_tax_is_distributed_by_subtotal(): void
    {
        $result = $this->calculator->calculate([
            'items' => [
                ['id' => 1, 'name' => 'Murah', 'quantity' => 1, 'unit_price' => 30000, 'total_price' => 30000],
                ['id' => 2, 'name' => 'Mahal', 'quantity' => 1, 'unit_price' => 70000, 'total_price' => 70000],
            ],
            'participants' => [
                ['id' => 1, 'name' => 'A'],
                ['id' => 2, 'name' => 'B'],
            ],
            'allocations' => [
                ['item_id' => 1, 'type' => 'single', 'single_participant_id' => 1],
                ['item_id' => 2, 'type' => 'single', 'single_participant_id' => 2],
            ],
            'tax_amount' => 10000,
            'tax_rule' => 'proportional',
            'rounding_method' => 'none',
            'grand_total' => 110000,
        ]);

        $shares = collect($result['shares'])->keyBy('participant_id');

        $this->assertSame(3000, $shares[1]['tax_share']);
        $this->assertSame(7000, $shares[2]['tax_share']);
        $this->assertSame(33000, $shares[1]['final_total']);
        $this->assertSame(77000, $shares[2]['final_total']);
        $this->assertTrue($result['is_matched']);
    }

    public function test_unallocated_items_are_reported(): void
    {
        $result = $this->calculator->calculate([
            'items' => [
                ['id' => 9, 'name' => 'Belum dibagi', 'quantity' => 1, 'unit_price' => 12000, 'total_price' => 12000],
            ],
            'participants' => [
                ['id' => 1, 'name' => 'A'],
            ],
            'allocations' => [],
            'rounding_method' => 'none',
            'grand_total' => 12000,
        ]);

        $this->assertSame([9], $result['unallocated_item_ids']);
    }

    public function test_rounding_methods_round_to_the_nearest_step(): void
    {
        $this->assertSame(1200, $this->calculator->applyRounding(1240, 'nearest_100'));
        $this->assertSame(1500, $this->calculator->applyRounding(1260, 'nearest_500'));
        $this->assertSame(1000, $this->calculator->applyRounding(1499, 'nearest_1000'));
        $this->assertSame(1243, $this->calculator->applyRounding(1243.4, 'none'));
    }

    public function test_payer_attribution_and_minimal_transfers(): void
    {
        // A buys 12.000, B buys 15.000, C buys 3.000; everything split three ways.
        $result = $this->calculator->calculate([
            'items' => [
                ['id' => 1, 'name' => 'Makan', 'quantity' => 1, 'unit_price' => 12000, 'total_price' => 12000],
                ['id' => 2, 'name' => 'Minum', 'quantity' => 1, 'unit_price' => 15000, 'total_price' => 15000],
                ['id' => 3, 'name' => 'Jajan', 'quantity' => 1, 'unit_price' => 3000, 'total_price' => 3000],
            ],
            'participants' => [
                ['id' => 1, 'name' => 'A'],
                ['id' => 2, 'name' => 'B'],
                ['id' => 3, 'name' => 'C'],
            ],
            'allocations' => [
                ['item_id' => 1, 'type' => 'shared_equal', 'shared_participant_ids' => [1, 2, 3], 'payer_participant_id' => 1],
                ['item_id' => 2, 'type' => 'shared_equal', 'shared_participant_ids' => [1, 2, 3], 'payer_participant_id' => 2],
                ['item_id' => 3, 'type' => 'shared_equal', 'shared_participant_ids' => [1, 2, 3], 'payer_participant_id' => 3],
            ],
            'rounding_method' => 'none',
            'grand_total' => 30000,
        ]);

        $shares = collect($result['shares'])->keyBy('participant_id');

        $this->assertSame(12000, $shares[1]['paid_amount']);
        $this->assertSame(15000, $shares[2]['paid_amount']);
        $this->assertSame(3000, $shares[3]['paid_amount']);

        // Everyone owes 10.000, so A gets back 2.000, B gets back 5.000, C owes 7.000.
        $this->assertSame(2000, $result['balances'][1]['balance']);
        $this->assertSame(5000, $result['balances'][2]['balance']);
        $this->assertSame(-7000, $result['balances'][3]['balance']);

        $this->assertSame([
            ['from_id' => 3, 'from_name' => 'C', 'to_id' => 2, 'to_name' => 'B', 'amount' => 5000],
            ['from_id' => 3, 'from_name' => 'C', 'to_id' => 1, 'to_name' => 'A', 'amount' => 2000],
        ], $result['transfers']);
    }

    public function test_settlement_matches_the_four_person_example(): void
    {
        $result = $this->calculator->calculate([
            'items' => [
                ['id' => 1, 'name' => 'Manda', 'quantity' => 1, 'unit_price' => 60000, 'total_price' => 60000],
                ['id' => 2, 'name' => 'Iza', 'quantity' => 1, 'unit_price' => 90000, 'total_price' => 90000],
                ['id' => 3, 'name' => 'Rizka', 'quantity' => 1, 'unit_price' => 83000, 'total_price' => 83000],
                ['id' => 4, 'name' => 'Ainin', 'quantity' => 1, 'unit_price' => 39000, 'total_price' => 39000],
            ],
            'participants' => [
                ['id' => 1, 'name' => 'Manda'],
                ['id' => 2, 'name' => 'Iza'],
                ['id' => 3, 'name' => 'Rizka'],
                ['id' => 4, 'name' => 'Ainin'],
            ],
            'allocations' => [
                ['item_id' => 1, 'type' => 'shared_equal', 'shared_participant_ids' => [1, 2, 3, 4], 'payer_participant_id' => 1],
                ['item_id' => 2, 'type' => 'shared_equal', 'shared_participant_ids' => [1, 2, 3, 4], 'payer_participant_id' => 2],
                ['item_id' => 3, 'type' => 'shared_equal', 'shared_participant_ids' => [1, 2, 3, 4], 'payer_participant_id' => 3],
                ['item_id' => 4, 'type' => 'shared_equal', 'shared_participant_ids' => [1, 2, 3, 4], 'payer_participant_id' => 4],
            ],
            'rounding_method' => 'none',
            'grand_total' => 272000,
        ]);

        $shares = collect($result['shares'])->keyBy('participant_id');

        foreach ($shares as $share) {
            $this->assertSame(68000, $share['final_total']);
        }

        $this->assertSame([
            ['from_id' => 4, 'from_name' => 'Ainin', 'to_id' => 2, 'to_name' => 'Iza', 'amount' => 22000],
            ['from_id' => 4, 'from_name' => 'Ainin', 'to_id' => 3, 'to_name' => 'Rizka', 'amount' => 7000],
            ['from_id' => 1, 'from_name' => 'Manda', 'to_id' => 3, 'to_name' => 'Rizka', 'amount' => 8000],
        ], $result['transfers']);
    }

    public function test_paid_amount_reconciles_with_grand_total(): void
    {
        $result = $this->calculator->calculate([
            'items' => [
                ['id' => 1, 'name' => 'Murah', 'quantity' => 1, 'unit_price' => 30000, 'total_price' => 30000],
                ['id' => 2, 'name' => 'Mahal', 'quantity' => 1, 'unit_price' => 70000, 'total_price' => 70000],
            ],
            'participants' => [
                ['id' => 1, 'name' => 'A'],
                ['id' => 2, 'name' => 'B'],
            ],
            'allocations' => [
                ['item_id' => 1, 'type' => 'single', 'single_participant_id' => 1, 'payer_participant_id' => 1],
                ['item_id' => 2, 'type' => 'single', 'single_participant_id' => 2, 'payer_participant_id' => 2],
            ],
            'tax_amount' => 10000,
            'tax_rule' => 'proportional',
            'rounding_method' => 'none',
            'grand_total' => 110000,
        ]);

        $shares = collect($result['shares'])->keyBy('participant_id');

        // Tax is spread to whoever fronted the items.
        $this->assertSame(33000, $shares[1]['paid_amount']);
        $this->assertSame(77000, $shares[2]['paid_amount']);
        $this->assertSame(110000, $result['total_paid']);
        $this->assertSame(0, $result['balances'][1]['balance']);
        $this->assertSame(0, $result['balances'][2]['balance']);
        $this->assertSame([], $result['transfers']);
    }

    public function test_null_payer_falls_back_to_the_default_payer(): void
    {
        $result = $this->calculator->calculate([
            'items' => [
                ['id' => 1, 'name' => 'Bersama', 'quantity' => 1, 'unit_price' => 9000, 'total_price' => 9000],
            ],
            'participants' => [
                ['id' => 1, 'name' => 'A'],
                ['id' => 2, 'name' => 'B'],
                ['id' => 3, 'name' => 'C'],
            ],
            'allocations' => [
                ['item_id' => 1, 'type' => 'shared_equal', 'shared_participant_ids' => [1, 2, 3]],
            ],
            'default_payer_id' => 2,
            'rounding_method' => 'none',
            'grand_total' => 9000,
        ]);

        $shares = collect($result['shares'])->keyBy('participant_id');

        $this->assertSame(0, $shares[1]['paid_amount']);
        $this->assertSame(9000, $shares[2]['paid_amount']);
        $this->assertSame(0, $shares[3]['paid_amount']);
    }
}
