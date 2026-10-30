@extends('layouts.app')

@section('title', 'Riwayat Split Bill')

@section('content')
@php
    $tabs = [
        'all' => ['label' => 'Semua', 'count' => $allBills->count()],
        'pending' => ['label' => 'Belum Lunas', 'count' => $allBills->filter(fn ($bill) => $bill->isPending())->count()],
        'completed' => ['label' => 'Lunas', 'count' => $allBills->where('status', 'completed')->count()],
        'archived' => ['label' => 'Arsip', 'count' => $allBills->where('status', 'archived')->count()],
    ];
@endphp

<div class="space-y-4">
    <section class="card p-4 sm:p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <form method="GET" action="{{ route('history.index') }}" class="flex w-full items-center gap-2 lg:max-w-md">
                <input type="hidden" name="status" value="{{ $status }}">
                <div class="relative flex-1">
                    <i data-lucide="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted"></i>
                    <input type="search" name="q" value="{{ $search }}" class="input has-icon" placeholder="Cari nama struk, merchant, atau kode..." aria-label="Cari split bill">
                </div>
                <button type="submit" class="btn btn-ghost">Cari</button>
            </form>

            <div class="flex items-center gap-2">
                <a href="{{ route('split-bills.create') }}" class="btn btn-primary">
                    <i data-lucide="plus" class="h-4 w-4"></i> Split Baru
                </a>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-1.5 border-t border-line pt-3">
            @foreach ($tabs as $key => $tab)
                <a href="{{ route('history.index', array_filter(['status' => $key, 'q' => $search])) }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[11px] font-bold transition {{ $status === $key ? 'bg-primary text-white' : 'bg-[#f3f6f4] text-muted hover:text-ink' }}">
                    {{ $tab['label'] }}
                    <span class="rounded-full px-1.5 py-0.5 text-[9px] {{ $status === $key ? 'bg-white/20' : 'bg-white' }}">{{ $tab['count'] }}</span>
                </a>
            @endforeach
        </div>
    </section>

    @if ($bills->isEmpty())
        <section class="card px-5 py-14 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-light text-primary">
                <i data-lucide="search-x" class="h-6 w-6"></i>
            </span>
            <h3 class="mt-3 text-[15px] font-extrabold text-ink">Tidak ada split bill</h3>
            <p class="mx-auto mt-1 max-w-sm text-[11.5px] leading-relaxed text-muted">
                @if ($search !== '')
                    Tidak ada hasil untuk "{{ $search }}". Coba kata kunci lain atau ubah filter.
                @else
                    Belum ada data pada filter ini. Yuk buat split bill pertamamu.
                @endif
            </p>
            <a href="{{ route('split-bills.create') }}" class="btn btn-primary mt-4">Scan Struk Baru</a>
        </section>
    @else
        <section class="space-y-2.5">
            @foreach ($bills as $bill)
                @php
                    $paidCount = $bill->participants->where('payment_status', 'paid')->count();
                    $totalParticipants = max(1, $bill->participants->count());
                    $progress = (int) round($paidCount / $totalParticipants * 100);
                    $share = $bill->currentUserShare();
                @endphp

                <article class="card card-hover overflow-hidden">
                    <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
                        <div class="flex min-w-0 flex-1 items-start gap-3">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-light text-primary-dark">
                                <i data-lucide="{{ $bill->category === 'kafe' ? 'coffee' : ($bill->category === 'belanja' ? 'shopping-bag' : ($bill->category === 'hadiah' ? 'gift' : 'utensils')) }}" class="h-5 w-5"></i>
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('split-bills.show', $bill) }}" class="truncate text-[13.5px] font-extrabold text-ink hover:text-primary-dark">{{ $bill->title }}</a>
                                    @include('partials.bill-status', ['bill' => $bill])
                                </div>
                                <p class="mt-0.5 text-[11px] font-medium text-muted">
                                    {{ $bill->merchant_name ?: 'Tanpa merchant' }} · {{ $bill->date?->translatedFormat('d M Y') }} · {{ $bill->code }}
                                </p>

                                <div class="mt-2 flex flex-wrap items-center gap-3">
                                    <div class="flex -space-x-1.5">
                                        @foreach ($bill->participants->take(4) as $participant)
                                            <span class="avatar-initial h-6 w-6 border-2 border-white text-[8px]" style="background: {{ $participant->color }}">{{ $participant->initials }}</span>
                                        @endforeach
                                        @if ($bill->participants->count() > 4)
                                            <span class="avatar-initial h-6 w-6 border-2 border-white bg-slate-500 text-[8px]">+{{ $bill->participants->count() - 4 }}</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="progress-track w-24">
                                            <div class="progress-fill" style="width: {{ $progress }}%"></div>
                                        </div>
                                        <span class="text-[9.5px] font-bold text-muted">{{ $paidCount }}/{{ $totalParticipants }} lunas</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4 border-t border-line pt-3 sm:border-0 sm:pt-0">
                            <div class="text-right sm:min-w-[110px]">
                                <p class="text-[9.5px] font-bold uppercase tracking-[0.1em] text-muted">Bagianmu</p>
                                <p class="text-[14px] font-extrabold text-ink hash">{{ format_rupiah($share?->final_total ?? 0) }}</p>
                                <p class="text-[9.5px] text-muted">dari {{ format_rupiah($bill->grand_total) }}</p>
                            </div>

                            <div class="flex items-center gap-1">
                                <a href="{{ route('split-bills.show', $bill) }}" class="icon-btn border border-line bg-white" title="Detail">
                                    <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                                </a>
                                <button type="button" class="icon-btn border border-line bg-white text-danger" title="Hapus"
                                        data-confirm-delete
                                        data-confirm-title="Hapus split bill?"
                                        data-confirm-message="Split bill &quot;{{ $bill->title }}&quot; akan dihapus permanen beserta data pembagiannya."
                                        data-confirm-url="{{ route('split-bills.destroy', $bill) }}"
                                        data-confirm-method="DELETE"
                                        data-confirm-label="Hapus">
                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>
    @endif
</div>
@endsection
