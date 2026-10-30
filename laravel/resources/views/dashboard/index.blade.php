@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-4">
    <section class="relative overflow-hidden rounded-[24px] bg-gradient-to-br from-[#14532d] via-[#166534] to-[#16a34a] p-5 text-white shadow-[0_30px_60px_-40px_rgba(22,101,52,0.85)] sm:p-7">
        <div class="absolute -right-16 -top-20 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute -bottom-24 right-20 h-56 w-56 rounded-full bg-[#10b981]/25 blur-3xl"></div>

        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-xl">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.14em] text-white/85 backdrop-blur">
                    <span class="pulse-dot bg-[#bbf7d0]"></span>
                    {{ now()->locale('id')->translatedFormat('l, d F Y') }}
                </span>
                <h2 class="mt-4 text-[26px] font-extrabold leading-tight tracking-tight sm:text-[32px]">
                    Halo, {{ $navUser->first_name }}!<br>
                    <span class="text-[#bbf7d0]">Ada yang mau dibagi hari ini?</span>
                </h2>
                <p class="mt-2 max-w-md text-[12.5px] leading-relaxed text-white/75">
                    Foto struk, pilih peserta, dan biarkan PatunganBeres menghitung bagian masing-masing secara adil.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row lg:flex-col">
                <a href="{{ route('split-bills.create') }}" class="btn bg-white text-primary-dark hover:bg-light justify-center">
                    <i data-lucide="scan-line" class="h-4 w-4"></i>
                    Scan Struk Baru
                </a>
                <a href="{{ route('iuran.index') }}" class="btn border-white/25 bg-white/10 text-white hover:bg-white/20 justify-center backdrop-blur">
                    <i data-lucide="piggy-bank" class="h-4 w-4"></i>
                    Kelola Iuran
                </a>
            </div>
        </div>
    </section>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="stat-card">
            <div class="relative z-10 flex items-start justify-between">
                <div>
                    <p class="text-[10.5px] font-bold uppercase tracking-[0.1em] text-muted">Total Bagianku</p>
                    <p class="mt-1.5 text-[19px] font-extrabold tracking-tight text-ink hash">{{ format_rupiah($totalSpent) }}</p>
                    <p class="mt-1 text-[10px] font-medium text-muted">dari split bill aktif</p>
                </div>
                <span class="stat-icon"><i data-lucide="wallet" class="h-4 w-4"></i></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="relative z-10 flex items-start justify-between">
                <div>
                    <p class="text-[10.5px] font-bold uppercase tracking-[0.1em] text-muted">Total Tagihan</p>
                    <p class="mt-1.5 text-[19px] font-extrabold tracking-tight text-ink hash">{{ format_rupiah($totalBillVolume) }}</p>
                    <p class="mt-1 text-[10px] font-medium text-muted">{{ $totalBillsCount }} split bill</p>
                </div>
                <span class="stat-icon"><i data-lucide="receipt-text" class="h-4 w-4"></i></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="relative z-10 flex items-start justify-between">
                <div>
                    <p class="text-[10.5px] font-bold uppercase tracking-[0.1em] text-muted">Menunggu</p>
                    <p class="mt-1.5 text-[19px] font-extrabold tracking-tight text-ink">{{ $pendingBillsCount }}</p>
                    <p class="mt-1 text-[10px] font-medium text-muted">perlu diselesaikan</p>
                </div>
                <span class="stat-icon bg-warning/10 text-warning"><i data-lucide="hourglass" class="h-4 w-4"></i></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="relative z-10 flex items-start justify-between">
                <div>
                    <p class="text-[10.5px] font-bold uppercase tracking-[0.1em] text-muted">Selesai</p>
                    <p class="mt-1.5 text-[19px] font-extrabold tracking-tight text-ink">{{ $completedBillsCount }}</p>
                    <p class="mt-1 text-[10px] font-medium text-muted">split bill lunas</p>
                </div>
                <span class="stat-icon"><i data-lucide="badge-check" class="h-4 w-4"></i></span>
            </div>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
        <div class="card p-4 sm:p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-[15px] font-extrabold tracking-tight text-ink">Perlu Diselesaikan</h3>
                    <p class="mt-0.5 text-[11px] font-medium text-muted">Split bill yang belum lunas sepenuhnya</p>
                </div>
                <a href="{{ route('history.index', ['status' => 'pending']) }}" class="text-[11px] font-bold text-primary hover:underline">Lihat semua</a>
            </div>

            <div class="mt-3.5 space-y-2.5">
                @forelse ($pendingBills->take(3) as $bill)
                    @php
                        $paidCount = $bill->participants->where('payment_status', 'paid')->count();
                        $totalParticipants = max(1, $bill->participants->count());
                        $progress = (int) round($paidCount / $totalParticipants * 100);
                    @endphp
                    <a href="{{ route('split-bills.show', $bill) }}" class="block rounded-2xl border border-line p-3 transition hover:border-primary/35 hover:bg-light/20">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-light text-primary-dark">
                                <i data-lucide="{{ $bill->category === 'kafe' ? 'coffee' : ($bill->category === 'belanja' ? 'shopping-bag' : 'utensils') }}" class="h-[18px] w-[18px]"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-[12.5px] font-bold text-ink">{{ $bill->title }}</p>
                                        <p class="mt-0.5 text-[10.5px] font-medium text-muted">{{ $bill->date?->translatedFormat('d M Y') }} · {{ $bill->code }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[12.5px] font-extrabold text-ink hash">{{ format_rupiah($bill->currentUserShare()?->final_total ?? $bill->grand_total) }}</p>
                                        <p class="text-[9.5px] font-medium text-muted">bagianmu</p>
                                    </div>
                                </div>

                                <div class="mt-2 flex items-center gap-2">
                                    <div class="progress-track">
                                        <div class="progress-fill" style="width: {{ $progress }}%"></div>
                                    </div>
                                    <span class="text-[9.5px] font-bold text-muted">{{ $paidCount }}/{{ $totalParticipants }}</span>
                                </div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-line px-4 py-8 text-center">
                        <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-light text-primary">
                            <i data-lucide="party-popper" class="h-5 w-5"></i>
                        </span>
                        <p class="mt-2 text-[12px] font-bold text-ink">Semua beres!</p>
                        <p class="mt-0.5 text-[10.5px] text-muted">Tidak ada tagihan yang menunggu.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="card p-4 sm:p-5">
            <h3 class="text-[15px] font-extrabold tracking-tight text-ink">Aksi Cepat</h3>
            <p class="mt-0.5 text-[11px] font-medium text-muted">Yang paling sering kamu pakai</p>

            <div class="mt-3.5 grid grid-cols-2 gap-2.5">
                <a href="{{ route('split-bills.create') }}" class="rounded-2xl border border-line p-3 transition hover:border-primary/35 hover:bg-light/30">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-light text-primary-dark"><i data-lucide="scan-line" class="h-4 w-4"></i></span>
                    <p class="mt-2 text-[11.5px] font-bold text-ink">Scan Struk</p>
                    <p class="text-[10px] text-muted">AI baca otomatis</p>
                </a>
                <a href="{{ route('iuran.index') }}" class="rounded-2xl border border-line p-3 transition hover:border-primary/35 hover:bg-light/30">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-light text-primary-dark"><i data-lucide="piggy-bank" class="h-4 w-4"></i></span>
                    <p class="mt-2 text-[11.5px] font-bold text-ink">Buat Iuran</p>
                    <p class="text-[10px] text-muted">Kas & patungan</p>
                </a>
                <a href="{{ route('history.index') }}" class="rounded-2xl border border-line p-3 transition hover:border-primary/35 hover:bg-light/30">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-light text-primary-dark"><i data-lucide="history" class="h-4 w-4"></i></span>
                    <p class="mt-2 text-[11.5px] font-bold text-ink">Riwayat</p>
                    <p class="text-[10px] text-muted">Semua split bill</p>
                </a>
                <a href="{{ route('profile.edit') }}" class="rounded-2xl border border-line p-3 transition hover:border-primary/35 hover:bg-light/30">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-light text-primary-dark"><i data-lucide="landmark" class="h-4 w-4"></i></span>
                    <p class="mt-2 text-[11.5px] font-bold text-ink">Rekening</p>
                    <p class="text-[10px] text-muted">Atur tujuan transfer</p>
                </a>
            </div>

            @if ($navDefaultBank)
                <div class="mt-4 rounded-2xl border border-primary/15 bg-light/50 p-3">
                    <p class="text-[9.5px] font-bold uppercase tracking-[0.12em] text-primary-dark">Rekening Utama</p>
                    <p class="mt-1 text-[12px] font-extrabold text-ink">{{ $navDefaultBank->bank }}</p>
                    <p class="text-[11px] font-semibold text-muted">{{ $navDefaultBank->account_number }} · {{ $navDefaultBank->account_holder }}</p>
                </div>
            @endif
        </div>
    </section>

    <section class="card p-4 sm:p-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-[15px] font-extrabold tracking-tight text-ink">Split Bill Terbaru</h3>
                <p class="mt-0.5 text-[11px] font-medium text-muted">Aktivitas patungan terakhir kamu</p>
            </div>
            <a href="{{ route('history.index') }}" class="btn btn-ghost btn-sm">
                Semua riwayat <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
            </a>
        </div>

        <div class="mt-3.5 grid gap-2.5 sm:grid-cols-2">
            @forelse ($recentBills as $bill)
                @php
                    $paidCount = $bill->participants->where('payment_status', 'paid')->count();
                @endphp
                <a href="{{ route('split-bills.show', $bill) }}" class="card card-hover flex items-center gap-3 p-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-light text-primary-dark">
                        <i data-lucide="{{ $bill->category === 'kafe' ? 'coffee' : ($bill->category === 'belanja' ? 'shopping-bag' : 'utensils') }}" class="h-5 w-5"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[12.5px] font-bold text-ink">{{ $bill->title }}</p>
                        <p class="mt-0.5 text-[10.5px] font-medium text-muted">{{ $bill->date?->translatedFormat('d M Y') }} · {{ $paidCount }}/{{ $bill->participants->count() }} lunas</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[12.5px] font-extrabold text-ink hash">{{ format_rupiah($bill->grand_total) }}</p>
                        <div class="mt-1 flex justify-end">@include('partials.bill-status', ['bill' => $bill])</div>
                    </div>
                </a>
            @empty
                <div class="sm:col-span-2 rounded-2xl border border-dashed border-line px-4 py-10 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-light text-primary">
                        <i data-lucide="receipt-text" class="h-5 w-5"></i>
                    </span>
                    <p class="mt-2 text-[12.5px] font-bold text-ink">Belum ada split bill</p>
                    <p class="mt-1 text-[11px] text-muted">Mulai dengan memindai struk pertamamu.</p>
                    <a href="{{ route('split-bills.create') }}" class="btn btn-primary btn-sm mt-3">Scan Struk Baru</a>
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection
