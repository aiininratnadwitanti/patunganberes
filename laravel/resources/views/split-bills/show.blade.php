@extends('layouts.app')

@section('title', 'Detail Split Bill')

@section('content')
@php
    $paidCount = $bill->participants->where('payment_status', 'paid')->count();
    $totalParticipants = max(1, $bill->participants->count());
    $progress = (int) round($paidCount / $totalParticipants * 100);
    $participantNames = $bill->participants->pluck('name', 'id');
    $allocations = $bill->allocations->keyBy('receipt_item_id');
    $share = $bill->currentUserShare();
@endphp

<div class="space-y-4">
    <section class="card overflow-hidden">
        <div class="flex flex-col gap-4 p-4 sm:p-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-start gap-3">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-light text-primary-dark">
                    <i data-lucide="{{ $bill->category === 'kafe' ? 'coffee' : ($bill->category === 'belanja' ? 'shopping-bag' : 'utensils') }}" class="h-5 w-5"></i>
                </span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-[18px] font-extrabold leading-tight tracking-tight text-ink">{{ $bill->title }}</h2>
                        @include('partials.bill-status', ['bill' => $bill])
                    </div>
                    <p class="mt-1 text-[11px] font-medium text-muted">
                        {{ $bill->merchant_name ?: 'Tanpa merchant' }} · {{ $bill->date?->translatedFormat('d F Y') }} {{ $bill->time }} · {{ $bill->code }}
                    </p>
                    <div class="mt-2.5 flex items-center gap-2">
                        <div class="flex -space-x-1.5">
                            @foreach ($bill->participants->take(5) as $p)
                                <span class="avatar-initial h-7 w-7 border-2 border-white text-[8px]" style="background: {{ $p->color }}">{{ $p->initials }}</span>
                            @endforeach
                        </div>
                        <span class="text-[10px] font-semibold text-muted">{{ $bill->participants->count() }} peserta</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-1.5">
                @if ($bill->status === 'draft')
                    <a href="{{ route('split-bills.validate', $bill) }}" class="btn btn-primary btn-sm">
                        <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Lanjutkan Draft
                    </a>
                @else
                    <a href="{{ route('split-bills.receipts', $bill) }}" class="btn btn-primary btn-sm">
                        <i data-lucide="share-2" class="h-3.5 w-3.5"></i> Tagihan & Link
                    </a>
                @endif
                <form method="POST" action="{{ route('split-bills.duplicate', $bill) }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm"><i data-lucide="copy" class="h-3.5 w-3.5"></i> Duplikasi</button>
                </form>
                <form method="POST" action="{{ route('split-bills.archive', $bill) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-ghost btn-sm"><i data-lucide="archive" class="h-3.5 w-3.5"></i> Arsipkan</button>
                </form>
                <button type="button" class="btn btn-ghost btn-sm text-danger"
                        data-confirm-delete
                        data-confirm-title="Hapus split bill?"
                        data-confirm-message="Split bill &quot;{{ $bill->title }}&quot; akan dihapus permanen."
                        data-confirm-url="{{ route('split-bills.destroy', $bill) }}"
                        data-confirm-method="DELETE"
                        data-confirm-label="Hapus">
                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Hapus
                </button>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 border-t border-line bg-[#f9fbf9] p-4 sm:grid-cols-4">
            <div>
                <p class="text-[9.5px] font-bold uppercase tracking-[0.1em] text-muted">Total Tagihan</p>
                <p class="mt-1 text-[16px] font-extrabold text-ink hash">{{ format_rupiah($bill->grand_total) }}</p>
            </div>
            <div>
                <p class="text-[9.5px] font-bold uppercase tracking-[0.1em] text-muted">Bagianmu</p>
                <p class="mt-1 text-[16px] font-extrabold text-primary-dark hash">{{ format_rupiah($share?->final_total ?? 0) }}</p>
            </div>
            <div>
                <p class="text-[9.5px] font-bold uppercase tracking-[0.1em] text-muted">Pelunasan</p>
                <p class="mt-1 text-[16px] font-extrabold text-ink">{{ $paidCount }}/{{ $totalParticipants }}</p>
                <div class="progress-track mt-1 h-1"><div class="progress-fill" style="width: {{ $progress }}%"></div></div>
            </div>
            <div>
                <p class="text-[9.5px] font-bold uppercase tracking-[0.1em] text-muted">Dibuat</p>
                <p class="mt-1 text-[12px] font-bold text-ink">{{ $bill->created_at?->translatedFormat('d M Y') }}</p>
                <p class="text-[9.5px] text-muted">{{ $bill->created_at?->format('H:i') }}</p>
            </div>
        </div>
    </section>

    @if ($bill->notes)
        <div class="flex items-start gap-2 rounded-xl border border-line bg-white px-3.5 py-2.5">
            <i data-lucide="sticky-note" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary"></i>
            <p class="text-[10.5px] leading-relaxed text-muted">{{ $bill->notes }}</p>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
        <div class="space-y-3">
            <section class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-line px-4 py-3">
                    <h3 class="text-[13px] font-extrabold text-ink">Peserta & Bagian</h3>
                    <span class="text-[10px] font-bold text-muted">{{ $bill->participants->count() }} orang</span>
                </div>

                <div class="divide-y divide-line">
                    @foreach ($bill->participants as $participant)
                        @php $pShare = $bill->shareFor($participant); @endphp
                        <div class="flex flex-wrap items-center gap-2.5 px-4 py-3 {{ $participant->is_current_user ? 'bg-light/20' : '' }}">
                            <span class="avatar-initial h-8 w-8 text-[10px]" style="background: {{ $participant->color }}">{{ $participant->initials }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[11.5px] font-bold text-ink">{{ $participant->name }} @if ($participant->is_current_user)<span class="text-[9px] font-bold text-primary">(Saya)</span>@endif</p>
                                <p class="text-[9.5px] text-muted">{{ $pShare ? count($pShare->items_list ?? []) : 0 }} item · {{ $participant->status_label }}</p>
                            </div>
                            <p class="text-[12.5px] font-extrabold text-ink hash">{{ format_rupiah($pShare?->final_total ?? 0) }}</p>

                            @if ($participant->payment_status === 'waiting_verification')
                                <div class="flex w-full items-center gap-1.5 border-t border-dashed border-line pt-2 sm:w-auto sm:border-0 sm:pt-0">
                                    <a href="{{ $participant->payment_proof_src }}" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm">Bukti</a>
                                    <form method="POST" action="{{ route('split-bills.payment.update', $bill) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="participant_id" value="{{ $participant->id }}">
                                        <input type="hidden" name="payment_status" value="paid">
                                        <button class="btn btn-primary btn-sm">Terima</button>
                                    </form>
                                    <form method="POST" action="{{ route('split-bills.payment.update', $bill) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="participant_id" value="{{ $participant->id }}">
                                        <input type="hidden" name="payment_status" value="needs_fix">
                                        <input type="hidden" name="verification_notes" value="Mohon unggah ulang bukti pembayaran.">
                                        <button class="btn btn-ghost btn-sm text-danger">Tolak</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-line px-4 py-3">
                    <h3 class="text-[13px] font-extrabold text-ink">Item & Pembagian</h3>
                    <span class="text-[10px] font-bold text-muted">{{ $bill->items->count() }} item</span>
                </div>

                <div class="divide-y divide-line">
                    @foreach ($bill->items as $item)
                        @php
                            $allocation = $allocations[$item->id] ?? null;
                            $allocationText = '-';
                            if ($allocation) {
                                if ($allocation->type === 'single') {
                                    $allocationText = $participantNames[$allocation->single_participant_id] ?? '-';
                                } elseif ($allocation->type === 'shared_equal') {
                                    $allocationText = collect($allocation->shared_participant_ids ?? [])->map(fn ($id) => $participantNames[$id] ?? null)->filter()->implode(', ');
                                    $allocationText = $allocationText ? 'Bagi rata: '.$allocationText : '-';
                                } else {
                                    $allocationText = collect($allocation->portion_assignments ?? [])->map(function ($portion) use ($participantNames) {
                                        $name = $participantNames[$portion['participant_id']] ?? null;
                                        return $name ? $name.' ('.$portion['quantity'].' porsi)' : null;
                                    })->filter()->implode(', ');
                                    $allocationText = $allocationText ?: '-';
                                }
                            }
                        @endphp
                        <div class="flex items-start justify-between gap-3 px-4 py-2.5">
                            <div class="min-w-0">
                                <p class="text-[11px] font-semibold text-ink">{{ $item->name }}</p>
                                <p class="mt-0.5 text-[9.5px] text-muted">{{ $item->quantity }} x {{ format_rupiah($item->unit_price) }} · {{ $allocationText }}</p>
                            </div>
                            <p class="shrink-0 text-[11px] font-bold text-ink hash">{{ format_rupiah($item->total_price) }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="space-y-3">
            <div class="card p-4">
                <h3 class="text-[12.5px] font-extrabold text-ink">Rincian Biaya</h3>
                <dl class="mt-2.5 space-y-1.5 text-[10.5px]">
                    <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd class="font-bold hash">{{ format_rupiah($bill->subtotal) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Pajak{{ $bill->is_tax_included ? ' (incl.)' : '' }}</dt><dd class="font-bold hash">{{ format_rupiah($bill->is_tax_included ? 0 : $bill->tax_amount) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Servis</dt><dd class="font-bold hash">{{ format_rupiah($bill->service_charge_amount) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Biaya lain</dt><dd class="font-bold hash">{{ format_rupiah($bill->other_fees_amount) }}</dd></div>
                    @if ($bill->discount_amount > 0)
                        <div class="flex justify-between text-danger"><dt>Diskon</dt><dd class="font-bold hash">-{{ format_rupiah($bill->discount_amount) }}</dd></div>
                    @endif
                    <div class="flex justify-between border-t border-dashed border-line pt-2 text-[12px]"><dt class="font-bold text-ink">Total</dt><dd class="font-extrabold text-primary-dark hash">{{ format_rupiah($bill->grand_total) }}</dd></div>
                </dl>
            </div>

            <div class="card p-4">
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted">Rekening Tujuan</p>
                @if ($defaultBank)
                    <p class="mt-1.5 text-[12.5px] font-extrabold text-ink">{{ $defaultBank->bank }}</p>
                    <p class="text-[11.5px] font-bold text-primary-dark">{{ $defaultBank->account_number }}</p>
                    <p class="text-[10px] text-muted">a.n. {{ $defaultBank->account_holder }}</p>
                @else
                    <p class="mt-1.5 text-[10.5px] text-muted">Belum ada rekening utama.</p>
                @endif
            </div>

            <div class="card p-4">
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted">Link Peserta</p>
                <div class="mt-2 space-y-1.5">
                    @foreach ($bill->participants as $participant)
                        <div class="flex items-center gap-2">
                            <span class="avatar-initial h-5 w-5 text-[8px]" style="background: {{ $participant->color }}">{{ $participant->initials }}</span>
                            <span class="min-w-0 flex-1 truncate text-[10px] font-semibold text-ink">{{ $participant->name }}</span>
                            <button type="button" class="text-[9.5px] font-bold text-primary hover:underline" data-copy="{{ guest_receipt_url($bill, $participant) }}" data-copy-message="Link {{ $participant->name }} disalin.">Salin</button>
                        </div>
                    @endforeach
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
