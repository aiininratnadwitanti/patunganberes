<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'related_id',
        'related_type',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getIconAttribute(): string
    {
        return match ($this->type) {
            'payment_reported' => 'send',
            'payment_verified' => 'badge-check',
            'payment_rejected', 'payment_updated' => 'circle-alert',
            'receipt_ready', 'bill_created' => 'receipt-text',
            'bill_completed' => 'party-popper',
            'iuran_created' => 'piggy-bank',
            'iuran_updated' => 'users',
            default => 'bell',
        };
    }

    public function getToneAttribute(): string
    {
        return match ($this->type) {
            'payment_verified', 'bill_completed' => 'primary',
            'payment_reported' => 'warning',
            'payment_rejected' => 'danger',
            default => 'neutral',
        };
    }
}
