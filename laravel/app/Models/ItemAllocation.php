<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemAllocation extends Model
{
    protected $fillable = [
        'split_bill_id',
        'receipt_item_id',
        'type',
        'single_participant_id',
        'shared_participant_ids',
        'portion_assignments',
    ];

    protected function casts(): array
    {
        return [
            'shared_participant_ids' => 'array',
            'portion_assignments' => 'array',
        ];
    }

    public function splitBill(): BelongsTo
    {
        return $this->belongsTo(SplitBill::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ReceiptItem::class, 'receipt_item_id');
    }

    public function singleParticipant(): BelongsTo
    {
        return $this->belongsTo(BillParticipant::class, 'single_participant_id');
    }
}
