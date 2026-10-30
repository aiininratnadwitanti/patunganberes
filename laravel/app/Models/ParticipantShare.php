<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantShare extends Model
{
    protected $fillable = [
        'split_bill_id',
        'bill_participant_id',
        'items_subtotal',
        'discount_share',
        'tax_share',
        'service_share',
        'other_fees_share',
        'rounding_adjustment',
        'final_total',
        'items_list',
    ];

    protected function casts(): array
    {
        return [
            'items_subtotal' => 'integer',
            'discount_share' => 'integer',
            'tax_share' => 'integer',
            'service_share' => 'integer',
            'other_fees_share' => 'integer',
            'rounding_adjustment' => 'integer',
            'final_total' => 'integer',
            'items_list' => 'array',
        ];
    }

    public function splitBill(): BelongsTo
    {
        return $this->belongsTo(SplitBill::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(BillParticipant::class, 'bill_participant_id');
    }

    public function getParticipantNameAttribute(): string
    {
        return $this->participant?->name ?? 'Peserta';
    }
}
