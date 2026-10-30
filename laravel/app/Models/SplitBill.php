<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SplitBill extends Model
{
    public const STATUSES = [
        'draft',
        'waiting_confirmation',
        'ready_to_share',
        'pending_payment',
        'partially_paid',
        'completed',
        'archived',
    ];

    public const PENDING_STATUSES = [
        'pending_payment',
        'waiting_confirmation',
        'ready_to_share',
        'partially_paid',
    ];

    protected $fillable = [
        'user_id',
        'code',
        'title',
        'merchant_name',
        'date',
        'time',
        'category',
        'receipt_image',
        'scanner_source',
        'subtotal',
        'tax_amount',
        'tax_rate_percent',
        'is_tax_included',
        'tax_rule',
        'service_charge_amount',
        'service_rate_percent',
        'service_rule',
        'discount_amount',
        'discount_rule',
        'other_fees_amount',
        'grand_total',
        'rounding_method',
        'status',
        'notes',
        'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_tax_included' => 'boolean',
            'is_locked' => 'boolean',
            'subtotal' => 'integer',
            'tax_amount' => 'integer',
            'tax_rate_percent' => 'integer',
            'service_charge_amount' => 'integer',
            'service_rate_percent' => 'integer',
            'discount_amount' => 'integer',
            'other_fees_amount' => 'integer',
            'grand_total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SplitBill $bill) {
            if (blank($bill->code)) {
                $bill->code = 'PB-'.now()->format('Y').'-'.random_int(100, 999);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReceiptItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(BillParticipant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ItemAllocation::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ParticipantShare::class);
    }

    public function currentParticipant(): ?BillParticipant
    {
        return $this->participants->firstWhere('is_current_user', true)
            ?? $this->participants->first();
    }

    public function shareFor(BillParticipant|int|null $participant): ?ParticipantShare
    {
        if (! $participant) {
            return null;
        }

        $id = $participant instanceof BillParticipant ? $participant->id : $participant;

        return $this->shares->firstWhere('bill_participant_id', $id);
    }

    public function currentUserShare(): ?ParticipantShare
    {
        return $this->shareFor($this->currentParticipant());
    }

    public function isPending(): bool
    {
        return in_array($this->status, self::PENDING_STATUSES, true);
    }

    public function getDisplayDateAttribute(): string
    {
        return $this->date ? $this->date->format('Y-m-d') : '';
    }

    public function getGuestUrlAttribute(): string
    {
        $participant = $this->currentParticipant();
        $token = $participant?->public_token;

        return $token ? route('guest.receipt', [$this->code, $token]) : route('split-bills.show', $this);
    }

    public function refreshPaymentStatus(): void
    {
        $participants = $this->participants;

        if ($participants->isEmpty()) {
            return;
        }

        $allPaid = $participants->every(fn (BillParticipant $p) => $p->payment_status === 'paid');

        if ($allPaid) {
            $this->status = 'completed';
        } elseif ($participants->contains(fn (BillParticipant $p) => $p->payment_status === 'paid' && ! $p->is_current_user)) {
            $this->status = 'partially_paid';
        } elseif ($this->status !== 'draft') {
            $this->status = 'pending_payment';
        }

        $this->save();
    }

    public function duplicateForUser(User $user): self
    {
        $copy = $this->replicate(['code', 'created_at', 'updated_at']);
        $copy->user_id = $user->id;
        $copy->code = 'PB-'.now()->format('Y').'-'.random_int(100, 999);
        $copy->title = $this->title.' (Salinan)';
        $copy->date = now()->toDateString();
        $copy->time = now()->format('H:i');
        $copy->status = 'draft';
        $copy->is_locked = false;
        $copy->save();

        $participantMap = [];

        foreach ($this->participants as $participant) {
            $newParticipant = $participant->replicate(['public_token', 'created_at', 'updated_at']);
            $newParticipant->split_bill_id = $copy->id;
            $newParticipant->public_token = Str::random(40);
            $newParticipant->payment_status = $participant->is_current_user ? 'paid' : 'unpaid';
            $newParticipant->paid_at = $participant->is_current_user ? now() : null;
            $newParticipant->payment_proof_url = null;
            $newParticipant->payment_method = null;
            $newParticipant->verification_notes = null;
            $newParticipant->save();
            $participantMap[$participant->id] = $newParticipant->id;
        }

        $itemMap = [];

        foreach ($this->items as $item) {
            $newItem = $item->replicate(['created_at', 'updated_at']);
            $newItem->split_bill_id = $copy->id;
            $newItem->save();
            $itemMap[$item->id] = $newItem->id;
        }

        foreach ($this->allocations as $allocation) {
            $newItemId = $itemMap[$allocation->receipt_item_id] ?? null;

            if (! $newItemId) {
                continue;
            }

            $newAllocation = $allocation->replicate(['created_at', 'updated_at']);
            $newAllocation->split_bill_id = $copy->id;
            $newAllocation->receipt_item_id = $newItemId;
            $newAllocation->single_participant_id = $allocation->single_participant_id
                ? ($participantMap[$allocation->single_participant_id] ?? null)
                : null;

            if (is_array($allocation->shared_participant_ids)) {
                $newAllocation->shared_participant_ids = array_values(array_filter(array_map(
                    fn ($id) => $participantMap[$id] ?? null,
                    $allocation->shared_participant_ids
                )));
            }

            if (is_array($allocation->portion_assignments)) {
                $newAllocation->portion_assignments = array_values(array_map(
                    fn ($assignment) => [
                        'participant_id' => $participantMap[$assignment['participant_id']] ?? null,
                        'quantity' => $assignment['quantity'] ?? 0,
                    ],
                    $allocation->portion_assignments
                ));
            }

            $newAllocation->save();
        }

        return $copy;
    }
}
