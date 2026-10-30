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
}
