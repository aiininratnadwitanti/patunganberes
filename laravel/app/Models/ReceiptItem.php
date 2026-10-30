<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReceiptItem extends Model
{
    protected $fillable = [
        'split_bill_id',
        'name',
        'quantity',
        'unit_price',
        'total_price',
        'confidence',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'total_price' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function splitBill(): BelongsTo
    {
        return $this->belongsTo(SplitBill::class);
    }

    public function allocation(): HasOne
    {
        return $this->hasOne(ItemAllocation::class);
    }
}
