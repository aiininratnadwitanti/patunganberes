@extends('layouts.guest')

@section('title', 'Struk Digital · '.$bill->title)

@section('content')
@php
    $items = $share->items_list ?? [];
    $isPaid = $participant->payment_status === 'paid';
    $isWaiting = $participant->payment_status === 'waiting_verification';
    $isNeedsFix = $participant->payment_status === 'needs_fix';
    $isReporting = $isWaiting || $isNeedsFix;

    $bankOptions = ['BCA', 'Bank Mandiri', 'BNI', 'BRI', 'QRIS', 'GoPay', 'OVO', 'DANA', 'ShopeePay'];
    $defaultMethod = $defaultBank?->bank && in_array($defaultBank->bank, $bankOptions, true)
        ? $defaultBank->bank
        : $bankOptions[0];
@endphp

<div class="mx-auto w-full max-w-md px-4 py-8 sm:py-12">
    <div class="mb-3 flex items-center justify-between">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-line bg-white px-3 py-1.5 text-[11px] font-bold text-primary-dark shadow-sm transition hover:bg-light">
            <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i> PatunganBeres
        </a>
        <span class="text-[10.5px] font-semibold text-muted">Tautan struk digital</span>
    </div>

    @if (! $share)
        <div class="card p-8 text-center">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-light text-primary"><i data-lucide="receipt-text" class="h-6 w-6"></i></span>
            <h3 class="mt-3 text-[15px] font-extrabold text-ink">Struk tidak ditemukan</h3>
            <p class="mx-auto mt-1 max-w-xs text-[11.5px] leading-relaxed text-muted">Tautan ini tidak valid atau sudah kedaluwarsa. Minta ulang tautan ke pembuat tagihan.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-3xl border-2 border-dashed border-line bg-white p-6 shadow-[0_30px_60px_-45px_rgba(23,35,28,0.6)] sm:p-8">
            <div class="border-b border-dashed border-line pb-5 text-center">
                <span class="brand-mark mx-auto h-12 w-12 rounded-2xl"><i data-lucide="receipt-text" class="h-6 w-6"></i></span>
                <h3 class="mt-2 text-[16px] font-extrabold tracking-tight text-ink">PATUNGAN<span class="text-primary">BERES</span></h3>
                <p class="mt-0.5 text-[10.5px] font-bold uppercase tracking-[0.18em] text-muted">Struk Split Bill Personal</p>
            </div>

            <dl class="space-y-1.5 border-b border-dashed border-line py-4 text-[11.5px]">
                <div class="flex justify-between gap-3">
                    <dt class="text-muted">ID Tagihan</dt>
                    <dd class="font-bold text-ink">{{ $bill->code }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-muted">Tempat</dt>
                    <dd class="truncate font-bold text-ink">{{ $bill->merchant_name ?: $bill->title }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-muted">Tanggal</dt>
                    <dd class="text-ink">{{ $bill->date?->translatedFormat('d F Y') }}{{ $bill->time ? ' · '.$bill->time : '' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3 pt-1">
                    <dt class="font-bold text-muted">Nama Peserta</dt>
                    <dd class="rounded-md bg-light px-2 py-0.5 text-[11.5px] font-extrabold text-primary-dark">{{ $participant->name }}</dd>
                </div>
            </dl>

            <div class="border-b border-dashed border-line py-4">
                <p class="mb-2 text-[10px] font-bold uppercase tracking-[0.14em] text-muted">Pesanan Kamu</p>
                <div class="space-y-2.5">
                    @forelse ($items as $item)
                        <div class="flex items-start justify-between gap-3 text-[11.5px]">
                            <div class="min-w-0">
                                <p class="truncate font-bold text-ink">{{ $item['name'] ?? 'Item' }}</p>
                                <p class="text-[10px] text-muted">{{ $item['portion_text'] ?? '' }}</p>
                            </div>
                            <p class="shrink-0 font-bold text-ink hash">{{ format_rupiah($item['price_share'] ?? 0) }}</p>
                        </div>
                    @empty
                        <p class="text-[11px] text-muted">Tidak ada item khusus untukmu pada tagihan ini.</p>
                    @endforelse
                </div>
            </div>

            <dl class="space-y-1.5 border-b border-dashed border-line py-4 text-[11.5px]">
                <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd class="font-bold text-ink hash">{{ format_rupiah($share->items_subtotal) }}</dd></div>
                @if ($share->discount_share > 0)
                    <div class="flex justify-between text-danger"><dt>Diskon</dt><dd class="font-bold hash">-{{ format_rupiah($share->discount_share) }}</dd></div>
                @endif
                @if ($share->tax_share > 0)
                    <div class="flex justify-between"><dt class="text-muted">Bagian PPN</dt><dd class="font-bold text-ink hash">{{ format_rupiah($share->tax_share) }}</dd></div>
                @endif
                @if ($share->service_share > 0)
                    <div class="flex justify-between"><dt class="text-muted">Biaya Layanan</dt><dd class="font-bold text-ink hash">{{ format_rupiah($share->service_share) }}</dd></div>
                @endif
                @if ($share->other_fees_share > 0)
                    <div class="flex justify-between"><dt class="text-muted">Biaya Lainnya</dt><dd class="font-bold text-ink hash">{{ format_rupiah($share->other_fees_share) }}</dd></div>
                @endif
                @if ($share->rounding_adjustment !== 0)
                    <div class="flex justify-between text-muted"><dt>Pembulatan</dt><dd class="hash">{{ $share->rounding_adjustment > 0 ? '+' : '' }}{{ format_rupiah($share->rounding_adjustment) }}</dd></div>
                @endif
            </dl>

            <div class="flex items-center justify-between border-b-2 border-dashed border-line py-4">
                <span class="text-[11px] font-extrabold uppercase tracking-[0.12em] text-ink">Total Tagihanmu</span>
                <span class="text-[22px] font-extrabold tracking-tight text-primary hash">{{ format_rupiah($share->final_total) }}</span>
            </div>

            <div class="pt-4">
                @if ($isPaid)
                    <div class="flex items-center justify-center gap-2 rounded-2xl border border-primary/25 bg-light/60 p-3 text-[11.5px] font-bold text-primary-dark">
                        <i data-lucide="badge-check" class="h-4 w-4"></i>
                        <span>Pembayaran sudah diverifikasi &amp; lunas</span>
                    </div>
                    @if ($participant->paid_at)
                        <p class="mt-2 text-center text-[10px] text-muted">Diverifikasi {{ $participant->paid_at->translatedFormat('d M Y, H:i') }}</p>
                    @endif
                @elseif ($isReporting)
                    <div class="flex items-center justify-center gap-2 rounded-2xl border border-warning/30 bg-warning/5 p-3 text-[11.5px] font-bold text-warning">
                        <i data-lucide="clock-4" class="h-4 w-4"></i>
                        <span>{{ $isNeedsFix ? 'Bukti perlu diperbaiki' : 'Bukti terkirim, menunggu konfirmasi' }}</span>
                    </div>
                    @if ($isNeedsFix && $participant->verification_notes)
                        <p class="mt-2 rounded-xl bg-danger/5 px-3 py-2 text-[10.5px] font-medium text-danger">Catatan: {{ $participant->verification_notes }}</p>
                    @endif

                    <form method="POST" action="{{ route('guest.receipt.report', [$bill->code, $participant->public_token]) }}" enctype="multipart/form-data" class="mt-3 space-y-2.5">
                        @csrf
                        <div>
                            <label class="label" for="payment_method">Metode Transfer</label>
                            <select class="select" id="payment_method" name="payment_method" required>
                                @foreach ($bankOptions as $option)
                                    <option value="{{ $option }}" @selected(old('payment_method', $participant->payment_method ?? $defaultMethod) === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="notes">Catatan (opsional)</label>
                            <input class="input" type="text" id="notes" name="notes" value="{{ old('notes') }}" placeholder="contoh: transfer via BCA Mobile a.n Rizka">
                        </div>
                        <div>
                            <label class="label" for="proof">Upload Ulang Bukti (opsional)</label>
                            <input class="input" type="file" id="proof" name="proof" accept="image/png,image/jpeg,image/webp">
                        </div>
                        <button type="submit" class="btn btn-primary w-full justify-center py-3">
                            <i data-lucide="send" class="h-4 w-4"></i> Kirim Ulang Konfirmasi
                        </button>
                    </form>
                @else
                    @if ($defaultBank)
                        <div class="rounded-2xl border border-line bg-[#f8faf9] p-3.5">
                            <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted">Silakan transfer ke</p>
                            <p class="mt-1 text-[13px] font-extrabold text-ink">{{ $defaultBank->bank }} · <span class="hash">{{ $defaultBank->account_number }}</span></p>
                            <p class="text-[10.5px] text-muted">a.n. {{ $defaultBank->account_holder }}</p>
                            <button type="button" class="mt-2 inline-flex items-center gap-1.5 text-[10.5px] font-bold text-primary hover:underline"
                                    data-copy="{{ $defaultBank->account_number }}" data-copy-message="Nomor rekening berhasil disalin.">
                                <i data-lucide="copy" class="h-3 w-3"></i> Salin Nomor Rekening
                            </button>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('guest.receipt.report', [$bill->code, $participant->public_token]) }}" enctype="multipart/form-data" class="mt-3 space-y-2.5">
                        @csrf
                        <div>
                            <label class="label" for="payment_method">Metode Transfer</label>
                            <select class="select" id="payment_method" name="payment_method" required>
                                @foreach ($bankOptions as $option)
                                    <option value="{{ $option }}" @selected(old('payment_method', $defaultMethod) === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                            @error('payment_method')
                                <p class="mt-1 text-[10.5px] font-semibold text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="label" for="notes">Catatan (opsional)</label>
                            <input class="input" type="text" id="notes" name="notes" value="{{ old('notes') }}" placeholder="contoh: transfer via BCA Mobile a.n Rizka">
                        </div>
                        <div>
                            <label class="label" for="proof">Bukti Transfer (opsional)</label>
                            <input class="input" type="file" id="proof" name="proof" accept="image/png,image/jpeg,image/webp">
                            @error('proof')
                                <p class="mt-1 text-[10.5px] font-semibold text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-full justify-center py-3">
                            <i data-lucide="upload" class="h-4 w-4"></i> Sudah Transfer &amp; Kirim Konfirmasi
                        </button>
                    </form>
                @endif
            </div>

            <p class="mt-5 border-t border-dashed border-line pt-4 text-center text-[9.5px] leading-relaxed text-muted">
                Ini adalah struk pembagian digital PatunganBeres, bukan faktur pajak resmi. Terima kasih sudah memakai PatunganBeres.
            </p>
        </div>
    @endif

    <p class="mt-4 text-center text-[10px] text-muted">Dibuat otomatis oleh PatunganBeres · Bagi tagihan tanpa drama.</p>
</div>
@endsection
