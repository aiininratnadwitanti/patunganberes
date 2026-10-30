<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class BillParticipant extends Model
{
    public const PAYMENT_STATUSES = [
        'unpaid',
        'waiting_verification',
        'paid',
        'needs_fix',
    ];

    protected $fillable = [
        'split_bill_id',
        'name',
        'initials',
        'color',
        'phone_or_wa',
        'is_current_user',
        'payment_status',
        'paid_at',
        'payment_proof_url',
        'payment_method',
        'verification_notes',
        'public_token',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_current_user' => 'boolean',
            'paid_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BillParticipant $participant) {
            if (blank($participant->public_token)) {
                $participant->public_token = Str::random(40);
            }

            if (blank($participant->initials)) {
                $participant->initials = Str::upper(Str::substr($participant->name, 0, 2));
            }
        });
    }

    public function splitBill(): BelongsTo
    {
        return $this->belongsTo(SplitBill::class);
    }

    public function share(): HasOne
    {
        return $this->hasOne(ParticipantShare::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'paid' => 'Lunas',
            'waiting_verification' => 'Menunggu Verifikasi',
            'needs_fix' => 'Perlu Perbaikan',
            default => 'Belum Bayar',
        };
    }

    public function getPaymentProofSrcAttribute(): ?string
    {
        if (blank($this->payment_proof_url)) {
            return null;
        }

        return str_starts_with($this->payment_proof_url, 'http')
            ? $this->payment_proof_url
            : asset('storage/'.$this->payment_proof_url);
    }
}
