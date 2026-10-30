@extends('layouts.app')

@section('title', 'Tagihan Siap Dibagikan')

@section('content')
@php
    $allLinks = $bill->participants
        ->map(fn ($participant) => [
            'name' => $participant->name,
            'url' => guest_receipt_url($bill, $participant),
        ])
        ->values();

    $allLinksText = $allLinks->map(fn ($link) => $link['name'].': '.$link['url'])->implode("\n");
    $allLinksMessage = 'Rincian tagihan *'.$bill->title.'*:'."\n\n".$allLinksText."\n\n— dibagikan via PatunganBeres";
@endphp

<div class="space-y-4">
    <section class="relative overflow-hidden rounded-[24px] border border-primary/20 bg-gradient-to-br from-light/80 via-white to-white p-5 sm:p-6">
        <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-primary/10 blur-2xl"></div>
        <div class="relative flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-3">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary text-white shadow-[0_12px_24px_-14px_rgba(22,163,74,0.9)]">
                    <i data-lucide="check-check" class="h-6 w-6"></i>
                </span>
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-primary-dark">Split bill berhasil dibuat</p>
                    <h2 class="mt-1 text-[22px] font-extrabold leading-tight tracking-tight text-ink sm:text-[26px]">Tagihan siap dibagikan!</h2>
                    <p class="mt-1 max-w-md text-[11.5px] leading-relaxed text-muted">Kirim link personal ke tiap peserta. Mereka bisa melihat rincian dan melaporkan pembayaran tanpa perlu membuat akun.</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn btn-ghost" data-copy="{{ $allLinksText }}" data-copy-message="Semua link peserta berhasil disalin.">
                    <i data-lucide="link" class="h-4 w-4"></i> Salin Semua Link
                </button>
                <a href="{{ whatsapp_url($allLinksMessage) }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
                    <i data-lucide="share-2" class="h-4 w-4"></i> Bagikan Semua
                </a>
            </div>
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
        <div class="space-y-3">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h3 class="text-[14px] font-extrabold tracking-tight text-ink">Tagihan per Peserta</h3>
                    <p class="text-[10.5px] text-muted">Klik WhatsApp untuk mengirim rincian personal.</p>
                </div>
                <span class="badge badge-neutral">{{ $bill->participants->count() }} peserta</span>
            </div>

            @foreach ($bill->participants as $participant)
                @php
                    $share = $bill->shareFor($participant);
                    $waUrl = $share ? whatsapp_url(split_bill_whatsapp_message($bill, $share, $defaultBank)) : '#';
                    $guestUrl = guest_receipt_url($bill, $participant);
                @endphp

                <article class="card p-3.5 sm:p-4 {{ $participant->is_current_user ? 'border-primary/25' : '' }}">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="avatar-initial h-10 w-10 text-[11px]" style="background: {{ $participant->color }}">{{ $participant->initials }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <p class="truncate text-[12.5px] font-extrabold text-ink">{{ $participant->name }}</p>
                                @if ($participant->is_current_user)
                                    <span class="badge badge-success">Saya</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-[10px] font-medium text-muted">{{ $share ? count($share->items_list ?? []) : 0 }} item · {{ $participant->phone_or_wa ?: 'Tanpa nomor WA' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[15px] font-extrabold text-ink hash">{{ format_rupiah($share?->final_total ?? 0) }}</p>
                            @include('partials.payment-status', ['status' => $participant->payment_status])
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-1.5 border-t border-line pt-2.5">
                        <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm">
                            <i data-lucide="message-circle" class="h-3.5 w-3.5"></i> WhatsApp
                        </a>
                        <a href="{{ $guestUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm">
                            <i data-lucide="receipt-text" class="h-3.5 w-3.5"></i> Struk
                        </a>
                        <button type="button" class="btn btn-ghost btn-sm" data-copy="{{ $guestUrl }}" data-copy-message="Link untuk {{ $participant->name }} berhasil disalin.">
                            <i data-lucide="link" class="h-3.5 w-3.5"></i> Salin
                        </button>

                        <span class="ml-auto flex items-center gap-1.5">
                            @if ($participant->payment_status === 'waiting_verification')
                                <a href="{{ $participant->payment_proof_src }}" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm">
                                    <i data-lucide="image" class="h-3.5 w-3.5"></i> Bukti
                                </a>
                                <form method="POST" action="{{ route('split-bills.payment.update', $bill) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="participant_id" value="{{ $participant->id }}">
                                    <input type="hidden" name="payment_status" value="paid">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i data-lucide="check" class="h-3.5 w-3.5"></i> Terima
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('split-bills.payment.update', $bill) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="participant_id" value="{{ $participant->id }}">
                                    <input type="hidden" name="payment_status" value="needs_fix">
                                    <input type="hidden" name="verification_notes" value="Bukti pembayaran belum sesuai. Mohon unggah ulang.">
                                    <button type="submit" class="btn btn-ghost btn-sm text-danger">
                                        <i data-lucide="x" class="h-3.5 w-3.5"></i> Tolak
                                    </button>
                                </form>
                            @elseif ($participant->payment_status !== 'paid')
                                <form method="POST" action="{{ route('split-bills.payment.update', $bill) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="participant_id" value="{{ $participant->id }}">
                                    <input type="hidden" name="payment_status" value="paid">
                                    <button type="submit" class="btn btn-ghost btn-sm">
                                        <i data-lucide="check" class="h-3.5 w-3.5"></i> Tandai Lunas
                                    </button>
                                </form>
                            @else
                                <span class="text-[9.5px] font-semibold text-muted">{{ $participant->paid_at?->translatedFormat('d M H:i') }}</span>
                            @endif
                        </span>
                    </div>

                    @if ($participant->verification_notes && $participant->payment_status === 'needs_fix')
                        <p class="mt-2 rounded-lg bg-danger/5 px-2.5 py-1.5 text-[10px] font-medium text-danger">Catatan: {{ $participant->verification_notes }}</p>
                    @endif
                </article>
            @endforeach
        </div>

        <aside class="space-y-3 lg:sticky lg:top-[76px] lg:self-start">
            <div class="card p-4">
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted">Rekening Tujuan</p>
                @if ($defaultBank)
                    <p class="mt-1.5 text-[15px] font-extrabold text-ink">{{ $defaultBank->bank }}</p>
                    <p class="text-[13px] font-bold text-primary-dark">{{ $defaultBank->account_number }}</p>
                    <p class="text-[10.5px] text-muted">a.n. {{ $defaultBank->account_holder }}</p>
                    <button type="button" class="btn btn-ghost btn-sm mt-3 w-full justify-center" data-copy="{{ $defaultBank->account_number }}" data-copy-message="Nomor rekening berhasil disalin.">
                        <i data-lucide="copy" class="h-3.5 w-3.5"></i> Salin Nomor Rekening
                    </button>
                @else
                    <p class="mt-1.5 text-[11px] text-muted">Belum ada rekening utama.</p>
                    <a href="{{ route('profile.edit') }}" class="btn btn-outline btn-sm mt-3 w-full justify-center">Tambah Rekening</a>
                @endif
            </div>

            <div class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-line px-4 py-3">
                    <h3 class="text-[12.5px] font-extrabold text-ink">Struk Digital</h3>
                    <span class="text-[10px] font-bold text-muted">{{ $bill->items->count() }} item</span>
                </div>
                <div class="divide-y divide-line">
                    @foreach ($bill->items as $item)
                        <div class="flex items-start justify-between gap-2 px-4 py-2">
                            <div class="min-w-0">
                                <p class="truncate text-[10.5px] font-semibold text-ink">{{ $item->name }}</p>
                                <p class="text-[9.5px] text-muted">{{ $item->quantity }} x {{ format_rupiah($item->unit_price) }}</p>
                            </div>
                            <p class="shrink-0 text-[10.5px] font-bold text-ink hash">{{ format_rupiah($item->total_price) }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-line bg-[#f8faf9] px-4 py-3">
                    <div class="flex items-center justify-between text-[10.5px]">
                        <span class="text-muted">Subtotal</span>
                        <span class="font-bold text-ink hash">{{ format_rupiah($bill->subtotal) }}</span>
                    </div>
                    <div class="mt-1 flex items-center justify-between text-[10.5px]">
                        <span class="text-muted">Pajak & servis</span>
                        <span class="font-bold text-ink hash">{{ format_rupiah(($bill->is_tax_included ? 0 : $bill->tax_amount) + $bill->service_charge_amount) }}</span>
                    </div>
                    @if ($bill->discount_amount > 0)
                        <div class="mt-1 flex items-center justify-between text-[10.5px] text-danger">
                            <span>Diskon</span>
                            <span class="font-bold hash">-{{ format_rupiah($bill->discount_amount) }}</span>
                        </div>
                    @endif
                    <div class="mt-2 flex items-end justify-between border-t border-dashed border-line pt-2">
                        <span class="text-[10px] font-bold uppercase tracking-wide text-muted">Total</span>
                        <span class="text-[15px] font-extrabold text-primary-dark hash">{{ format_rupiah($bill->grand_total) }}</span>
                    </div>
                </div>
            </div>

            <div class="card p-4">
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted">Aksi Cepat</p>
                <div class="mt-2 space-y-1.5">
                    <a href="{{ route('split-bills.show', $bill) }}" class="btn btn-ghost btn-sm w-full justify-start">
                        <i data-lucide="panel-top" class="h-3.5 w-3.5"></i> Lihat Detail Split Bill
                    </a>
                    <button type="button" class="btn btn-ghost btn-sm w-full justify-start no-print" onclick="window.print()">
                        <i data-lucide="printer" class="h-3.5 w-3.5"></i> Cetak / Simpan PDF
                    </button>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
