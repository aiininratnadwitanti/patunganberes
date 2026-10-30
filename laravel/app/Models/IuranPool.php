<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class IuranPool extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'public_token',
        'title',
        'description',
        'target_amount',
        'current_collected',
        'fee_per_person',
        'pricing_type',
        'deadline',
        'category',
        'bank_name',
        'account_number',
        'account_holder',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (IuranPool $pool) {
            if (blank($pool->public_token)) {
                $pool->public_token = (string) Str::ulid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'target_amount' => 'integer',
            'current_collected' => 'integer',
            'fee_per_person' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(IuranParticipant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getProgressAttribute(): int
    {
        if ($this->target_amount <= 0) {
            return 0;
        }

        return (int) min(100, round($this->current_collected / $this->target_amount * 100));
    }

    /**
     * Setoran reduce the remaining target the moment they are reported, so
     * both "paid" and "waiting_verification" amounts count. Rejected setoran
     * stop counting.
     */
    public function refreshCollected(): void
    {
        $this->current_collected = (int) $this->participants()
            ->whereIn('status', ['paid', 'waiting_verification'])
            ->sum('paid_amount');

        $this->status = ($this->target_amount > 0 && $this->current_collected >= $this->target_amount)
            ? 'completed'
            : 'active';

        $this->save();
    }
}
