<?php

namespace App\Services;

use App\Models\SplitBill;

class SplitBillCalculator
{
    /**
     * Calculate shares from a bill and optionally persist them.
     *
     * @return array{shares: array<int, array<string, mixed>>, total_allocated: int, difference_from_grand_total: int, is_matched: bool, unallocated_item_ids: array<int, int>}
     */
    public function calculateForBill(SplitBill $bill, bool $persist = false): array
    {
        $bill->loadMissing(['items', 'participants', 'allocations']);

        $items = $bill->items->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'total_price' => $item->total_price,
        ])->all();

        $participants = $bill->participants->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
        ])->all();

        $allocations = $bill->allocations->map(fn ($allocation) => [
            'item_id' => $allocation->receipt_item_id,
            'type' => $allocation->type,
            'single_participant_id' => $allocation->single_participant_id,
            'payer_participant_id' => $allocation->payer_participant_id,
            'shared_participant_ids' => $allocation->shared_participant_ids ?? [],
            'portion_assignments' => $allocation->portion_assignments ?? [],
        ])->all();

        $defaultPayer = $bill->participants->firstWhere('is_current_user', true)
            ?? $bill->participants->first();

        $result = $this->calculate([
            'items' => $items,
            'participants' => $participants,
            'allocations' => $allocations,
            'default_payer_id' => $defaultPayer?->id,
            'tax_amount' => $bill->is_tax_included ? 0 : $bill->tax_amount,
            'is_tax_included' => $bill->is_tax_included,
            'tax_rule' => $bill->tax_rule,
            'service_charge_amount' => $bill->service_charge_amount,
            'service_rule' => $bill->service_rule,
            'discount_amount' => $bill->discount_amount,
            'discount_rule' => $bill->discount_rule,
            'other_fees_amount' => $bill->other_fees_amount,
            'rounding_method' => $bill->rounding_method,
            'grand_total' => $bill->grand_total,
        ]);

        if ($persist) {
            $this->persist($bill, $result['shares']);
        }

        return $result;
    }

    /**
     * Port of calculateSplitBillShares() from the original React app.
     */
    public function calculate(array $params): array
    {
        $items = $params['items'] ?? [];
        $participants = $params['participants'] ?? [];
        $allocations = $params['allocations'] ?? [];
        $taxAmount = (int) ($params['tax_amount'] ?? 0);
        $isTaxIncluded = (bool) ($params['is_tax_included'] ?? false);
        $taxRule = $params['tax_rule'] ?? 'proportional';
        $serviceChargeAmount = (int) ($params['service_charge_amount'] ?? 0);
        $serviceRule = $params['service_rule'] ?? 'proportional';
        $discountAmount = (int) ($params['discount_amount'] ?? 0);
        $discountRule = $params['discount_rule'] ?? 'proportional';
        $otherFeesAmount = (int) ($params['other_fees_amount'] ?? 0);
        $roundingMethod = $params['rounding_method'] ?? 'nearest_100';
        $grandTotal = (int) ($params['grand_total'] ?? 0);
        $defaultPayerId = $params['default_payer_id'] ?? null;

        $shares = [];
        $itemPaid = [];

        foreach ($participants as $participant) {
            $shares[$participant['id']] = [
                'participant_id' => $participant['id'],
                'participant_name' => $participant['name'],
                'items_subtotal' => 0,
                'items_list' => [],
                'discount_share' => 0,
                'tax_share' => 0,
                'service_share' => 0,
                'other_fees_share' => 0,
                'rounding_adjustment' => 0,
                'final_total' => 0,
            ];
        }

        $unallocatedItemIds = [];

        foreach ($items as $item) {
            $alloc = null;
            foreach ($allocations as $candidate) {
                if ((string) $candidate['item_id'] === (string) $item['id']) {
                    $alloc = $candidate;
                    break;
                }
            }

            // Attribute who fronted the money for this item. Null falls back to
            // the bill owner (current user), then to the first participant.
            $payerId = $alloc['payer_participant_id'] ?? null;

            if (! $payerId || ! isset($shares[$payerId])) {
                $payerId = ($defaultPayerId && isset($shares[$defaultPayerId]))
                    ? $defaultPayerId
                    : array_key_first($shares);
            }

            if ($payerId !== null) {
                $itemPaid[$payerId] = ($itemPaid[$payerId] ?? 0) + (int) $item['total_price'];
            }

            if (! $alloc) {
                $unallocatedItemIds[] = $item['id'];

                continue;
            }

            $type = $alloc['type'] ?? null;

            if ($type === 'single') {
                $pid = $alloc['single_participant_id'] ?? null;

                if ($pid && isset($shares[$pid])) {
                    $shares[$pid]['items_subtotal'] += (int) $item['total_price'];
                    $shares[$pid]['items_list'][] = [
                        'item_id' => $item['id'],
                        'name' => $item['name'],
                        'portion_text' => $item['quantity'].' porsi (pribadi)',
                        'quantity' => $item['quantity'],
                        'price_share' => (int) $item['total_price'],
                    ];
                } else {
                    $unallocatedItemIds[] = $item['id'];
                }
            } elseif ($type === 'shared_equal') {
                $selectedIds = $alloc['shared_participant_ids'] ?? [];

                if (count($selectedIds) > 0) {
                    $pricePerPerson = (int) round($item['total_price'] / count($selectedIds));
                    $remainder = (int) $item['total_price'] - ($pricePerPerson * count($selectedIds));

                    foreach (array_values($selectedIds) as $index => $pid) {
                        if (! isset($shares[$pid])) {
                            continue;
                        }

                        $adjustedPrice = $pricePerPerson + ($index === 0 ? $remainder : 0);
                        $shares[$pid]['items_subtotal'] += $adjustedPrice;
                        $shares[$pid]['items_list'][] = [
                            'item_id' => $item['id'],
                            'name' => $item['name'],
                            'portion_text' => 'Bagi '.count($selectedIds).' orang',
                            'quantity' => round($item['quantity'] / count($selectedIds), 2),
                            'price_share' => $adjustedPrice,
                        ];
                    }
                } else {
                    $unallocatedItemIds[] = $item['id'];
                }
            } elseif ($type === 'by_portion') {
                $assignments = $alloc['portion_assignments'] ?? [];
                $totalAssignedQty = 0;

                foreach ($assignments as $assignment) {
                    $totalAssignedQty += (int) ($assignment['quantity'] ?? 0);
                }

                if ($totalAssignedQty === 0) {
                    $unallocatedItemIds[] = $item['id'];

                    continue;
                }

                foreach ($assignments as $assignment) {
                    $qty = (int) ($assignment['quantity'] ?? 0);
                    $pid = $assignment['participant_id'] ?? null;

                    if ($qty > 0 && $pid && isset($shares[$pid])) {
                        $portionPrice = (int) round($qty * $item['unit_price']);
                        $shares[$pid]['items_subtotal'] += $portionPrice;
                        $shares[$pid]['items_list'][] = [
                            'item_id' => $item['id'],
                            'name' => $item['name'],
                            'portion_text' => $qty.' porsi',
                            'quantity' => $qty,
                            'price_share' => $portionPrice,
                        ];
                    }
                }
            }
        }

        $totalSubtotal = array_sum(array_column($shares, 'items_subtotal'));
        $participantCount = max(1, count($participants));

        if ($discountAmount > 0) {
            if ($discountRule === 'proportional' && $totalSubtotal > 0) {
                $this->distributeProportional($shares, 'discount_share', $discountAmount, $totalSubtotal);
            } elseif ($discountRule === 'equal') {
                $this->distributeEqual($shares, 'discount_share', $discountAmount, $participantCount);
            }
        }

        $actualTaxToDistribute = $isTaxIncluded ? 0 : $taxAmount;

        if ($actualTaxToDistribute > 0) {
            if ($taxRule === 'proportional' && $totalSubtotal > 0) {
                $this->distributeProportional($shares, 'tax_share', $actualTaxToDistribute, $totalSubtotal);
            } elseif ($taxRule === 'equal') {
                $this->distributeEqual($shares, 'tax_share', $actualTaxToDistribute, $participantCount);
            }
        }

        if ($serviceChargeAmount > 0) {
            if ($serviceRule === 'proportional' && $totalSubtotal > 0) {
                $this->distributeProportional($shares, 'service_share', $serviceChargeAmount, $totalSubtotal);
            } elseif ($serviceRule === 'equal') {
                $this->distributeEqual($shares, 'service_share', $serviceChargeAmount, $participantCount);
            }
        }

        if ($otherFeesAmount > 0) {
            $this->distributeEqual($shares, 'other_fees_share', $otherFeesAmount, $participantCount);
        }

        $runningAllocatedTotal = 0;

        foreach ($shares as &$share) {
            $rawTotal = $share['items_subtotal']
                - $share['discount_share']
                + $share['tax_share']
                + $share['service_share']
                + $share['other_fees_share'];

            $rounded = $this->applyRounding($rawTotal, $roundingMethod);
            $share['rounding_adjustment'] = $rounded - $rawTotal;
            $share['final_total'] = max(0, $rounded);
            $runningAllocatedTotal += $share['final_total'];
        }
        unset($share);

        $targetGrandTotal = $grandTotal > 0
            ? $grandTotal
            : ($totalSubtotal - $discountAmount + $actualTaxToDistribute + $serviceChargeAmount + $otherFeesAmount);

        $difference = $runningAllocatedTotal - $targetGrandTotal;

        if (count($shares) > 0 && $difference !== 0 && abs($difference) < 2000) {
            $largestKey = null;
            $largestTotal = -1;
            foreach ($shares as $key => $share) {
                if ($share['final_total'] > $largestTotal) {
                    $largestTotal = $share['final_total'];
                    $largestKey = $key;
                }
            }

            if ($largestKey !== null) {
                $adjusted = max(0, $shares[$largestKey]['final_total'] - $difference);
                $delta = $adjusted - $shares[$largestKey]['final_total'];
                $shares[$largestKey]['final_total'] = $adjusted;
                $shares[$largestKey]['rounding_adjustment'] += $delta;
                $runningAllocatedTotal += $delta;
            }
        }

        $finalDifference = $runningAllocatedTotal - $targetGrandTotal;

        // Who actually fronted the money: the item payers, plus the non-item
        // charges (tax/service/other minus discount) spread proportionally so
        // the total paid always reconciles with the bill's grand total.
        $allItemsTotal = 0;
        foreach ($items as $item) {
            $allItemsTotal += (int) $item['total_price'];
        }

        $paidMap = $this->distributeFeesToPayers($shares, $itemPaid, $targetGrandTotal - $allItemsTotal);

        $balances = [];
        $names = [];

        foreach ($shares as $key => &$share) {
            $paid = (int) ($paidMap[$key] ?? 0);
            $share['paid_amount'] = $paid;
            $names[$key] = $share['participant_name'];
            $balances[$key] = [
                'participant_id' => $key,
                'paid' => $paid,
                'owed' => $share['final_total'],
                'balance' => $paid - $share['final_total'],
            ];
        }
        unset($share);

        $transfers = $this->computeSettlements($balances, $names);

        return [
            'shares' => array_values($shares),
            'total_allocated' => $runningAllocatedTotal,
            'difference_from_grand_total' => $finalDifference,
            'is_matched' => abs($finalDifference) === 0,
            'unallocated_item_ids' => $unallocatedItemIds,
            'balances' => $balances,
            'transfers' => $transfers,
            'total_paid' => array_sum($paidMap),
        ];
    }

    /**
     * Calculate balances and the minimal set of transfers for a bill without
     * persisting anything. Handy for read-only views.
     *
     * @return array<string, mixed>
     */
    public function settlementForBill(SplitBill $bill): array
    {
        return $this->calculateForBill($bill);
    }

    public function persist(SplitBill $bill, array $shares): void
    {
        foreach ($shares as $share) {
            $bill->shares()->updateOrCreate(
                ['bill_participant_id' => $share['participant_id']],
                [
                    'split_bill_id' => $bill->id,
                    'items_subtotal' => $share['items_subtotal'],
                    'paid_amount' => $share['paid_amount'] ?? 0,
                    'discount_share' => $share['discount_share'],
                    'tax_share' => $share['tax_share'],
                    'service_share' => $share['service_share'],
                    'other_fees_share' => $share['other_fees_share'],
                    'rounding_adjustment' => $share['rounding_adjustment'],
                    'final_total' => $share['final_total'],
                    'items_list' => $share['items_list'],
                ]
            );
        }

        $validIds = $bill->participants()->pluck('id')->all();
        $bill->shares()->whereNotIn('bill_participant_id', $validIds)->delete();
    }

    public function applyRounding(float $amount, string $method): int
    {
        return match ($method) {
            'nearest_100' => (int) (round($amount / 100) * 100),
            'nearest_500' => (int) (round($amount / 500) * 500),
            'nearest_1000' => (int) (round($amount / 1000) * 1000),
            default => (int) round($amount),
        };
    }

    /**
     * Spread non-item charges (net of discount) across the people who fronted
     * item money, proportionally to what each of them paid.
     *
     * @param  array<int|string, array<string, mixed>>  $shares
     * @param  array<int|string, int>  $itemPaid
     * @return array<int|string, int>
     */
    private function distributeFeesToPayers(array $shares, array $itemPaid, int $netFees): array
    {
        $paid = array_fill_keys(array_keys($shares), 0);

        foreach ($itemPaid as $participantId => $amount) {
            if (array_key_exists($participantId, $paid)) {
                $paid[$participantId] += (int) $amount;
            }
        }

        if ($netFees === 0) {
            return $paid;
        }

        $totalWeight = array_sum($paid);

        if ($totalWeight <= 0) {
            $firstKey = array_key_first($paid);

            if ($firstKey !== null) {
                $paid[$firstKey] += $netFees;
            }

            return $paid;
        }

        $allocated = 0;
        $keys = array_keys($paid);
        $lastKey = end($keys);

        foreach ($paid as $key => $amount) {
            if ($key === $lastKey) {
                $paid[$key] += $netFees - $allocated;
            } else {
                $part = (int) round(($amount / $totalWeight) * $netFees);
                $paid[$key] += $part;
                $allocated += $part;
            }
        }

        return $paid;
    }

    /**
     * Greedy debt simplification: match the biggest debtor with the biggest
     * creditor until every balance is settled.
     *
     * @param  array<int|string, array{participant_id: int|string, paid: int, owed: int, balance: int}>  $balances
     * @param  array<int|string, string>  $names
     * @return array<int, array{from_id: int|string, from_name: string, to_id: int|string, to_name: string, amount: int}>
     */
    private function computeSettlements(array $balances, array $names): array
    {
        $debtors = [];
        $creditors = [];

        foreach ($balances as $id => $entry) {
            if ($entry['balance'] < 0) {
                $debtors[] = ['id' => $id, 'balance' => $entry['balance']];
            } elseif ($entry['balance'] > 0) {
                $creditors[] = ['id' => $id, 'balance' => $entry['balance']];
            }
        }

        usort($debtors, fn ($a, $b) => $a['balance'] <=> $b['balance']);
        usort($creditors, fn ($a, $b) => $b['balance'] <=> $a['balance']);

        $transfers = [];
        $i = 0;
        $j = 0;

        while ($i < count($debtors) && $j < count($creditors)) {
            $amount = (int) min(-$debtors[$i]['balance'], $creditors[$j]['balance']);

            if ($amount > 0) {
                $transfers[] = [
                    'from_id' => $debtors[$i]['id'],
                    'from_name' => $names[$debtors[$i]['id']] ?? '',
                    'to_id' => $creditors[$j]['id'],
                    'to_name' => $names[$creditors[$j]['id']] ?? '',
                    'amount' => $amount,
                ];
            }

            $debtors[$i]['balance'] += $amount;
            $creditors[$j]['balance'] -= $amount;

            if ($debtors[$i]['balance'] >= 0) {
                $i++;
            }

            if ($creditors[$j]['balance'] <= 0) {
                $j++;
            }
        }

        return $transfers;
    }

    private function distributeProportional(array &$shares, string $field, int $amount, int $totalSubtotal): void
    {
        $allocated = 0;
        $keys = array_keys($shares);
        $lastKey = end($keys);

        foreach ($shares as $key => &$share) {
            if ($key === $lastKey) {
                $share[$field] = max(0, $amount - $allocated);
            } else {
                $part = (int) round(($share['items_subtotal'] / $totalSubtotal) * $amount);
                $share[$field] = $part;
                $allocated += $part;
            }
        }
        unset($share);
    }

    private function distributeEqual(array &$shares, string $field, int $amount, int $participantCount): void
    {
        $perPerson = intdiv($amount, $participantCount);
        $remainder = $amount - ($perPerson * $participantCount);

        foreach ($shares as &$share) {
            $share[$field] = $perPerson + ($remainder > 0 ? 1 : 0);
            if ($remainder > 0) {
                $remainder--;
            }
        }
        unset($share);
    }
}
