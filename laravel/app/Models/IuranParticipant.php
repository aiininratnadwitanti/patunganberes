<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IuranParticipant extends Model
{
    public const STATUSES = [
        'unpaid',
        'waiting_verification',
        'paid',
        'rejected',
    ];

    protected $fillable = [
        'iuran_pool_id',
        'name',
        'initials',
        'color',
        'target_fee',
        'paid_amount',
        'status',
        'proof_url',
        'paid_at',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'target_fee' => 'integer',
            'paid_amount' => 'integer',
            'paid_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (IuranParticipant $participant) {
            if (blank($participant->initials)) {
                $participant->initials = participant_initials($participant->name);
            }
        });
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(IuranPool::class, 'iuran_pool_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'Lunas',
            'waiting_verification' => 'Menunggu Verifikasi',
            'rejected' => 'Ditolak',
            default => 'Belum Bayar',
        };
    }
}
