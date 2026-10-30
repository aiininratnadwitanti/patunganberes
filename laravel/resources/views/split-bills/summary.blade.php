@extends('layouts.app')

@section('title', 'Ringkasan Pembagian')

@section('content')
@include('partials.wizard-steps', ['current' => 4, 'bill' => $bill])

<div class="space-y-4">
    @if (count($result['unallocated_item_ids']) > 0)
        <div class="flex flex-col gap-2 rounded-2xl border border-warning/30 bg-warning/5 p-3.5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-2.5">
                <i data-lucide="alert-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-warning"></i>
                <div>
                    <p class="text-[12px] font-extrabold text-ink">Masih ada {{ count($result['unallocated_item_ids']) }} item belum dibagi</p>
                    <p class="mt-0.5 text-[10.5px] leading-relaxed text-muted">Bagikan semua item terlebih dahulu agar total tagihan pas.</p>
                </div>
            </div>
            <a href="{{ route('split-bills.allocation', $bill) }}" class="btn btn-warning btn-sm shrink-0 justify-center">Bagi Item</a>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[1.45fr_1fr]">
        <div class="space-y-3">
            <section class="card overflow-hidden">
                <div class="border-b border-line bg-gradient-to-br from-light/70 to-white p-4 sm:p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-primary-dark">Total Tagihan</p>
                            <p class="mt-1 text-[30px] font-extrabold leading-none tracking-tight text-ink hash">{{ format_rupiah($bill->grand_total) }}</p>
                            <p class="mt-1 text-[11px] font-medium text-muted">{{ $bill->title }} · {{ $bill->participants->count() }} peserta</p>
                        </div>
                        <div class="text-left sm:text-right">
                            <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Terbagi</p>
                            <p class="text-[16px] font-extrabold text-ink hash">{{ format_rupiah($result['total_allocated']) }}</p>
                            @if ($result['is_matched'])
                                <span class="badge badge-success mt-1"><i data-lucide="check" class="h-3 w-3"></i> Pas</span>
                            @else
                                <span class="badge badge-warning mt-1">Selisih {{ format_rupiah($result['difference_from_grand_total']) }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="p-4 sm:p-5">
                    <h3 class="text-[13px] font-extrabold text-ink">Rincian Tagihan</h3>
                    <dl class="mt-2.5 space-y-2 text-[11.5px]">
                        <div class="flex justify-between"><dt class="text-muted">Subtotal item</dt><dd class="font-bold text-ink hash">{{ format_rupiah($bill->subtotal) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Pajak{{ $bill->is_tax_included ? ' (termasuk)' : '' }}</dt><dd class="font-bold text-ink hash">{{ format_rupiah($bill->is_tax_included ? 0 : $bill->tax_amount) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Biaya servis</dt><dd class="font-bold text-ink hash">{{ format_rupiah($bill->service_charge_amount) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Biaya lain</dt><dd class="font-bold text-ink hash">{{ format_rupiah($bill->other_fees_amount) }}</dd></div>
                        <div class="flex justify-between text-danger"><dt>Diskon</dt><dd class="font-bold hash">-{{ format_rupiah($bill->discount_amount) }}</dd></div>
                    </dl>
                </div>
            </section>

            <section class="card p-4 sm:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-[13px] font-extrabold text-ink">Aturan Pembagian</h3>
                        <p class="mt-0.5 text-[10.5px] text-muted">Ubah cara pajak, servis, dan diskon dibebankan.</p>
                    </div>
                    <span class="badge badge-neutral">{{ $bill->rounding_method === 'none' ? 'Tanpa pembulatan' : str_replace('nearest_', 'Bulat ', $bill->rounding_method) }}</span>
                </div>

                <form method="POST" action="{{ route('split-bills.rules.update', $bill) }}" class="mt-3 grid gap-2.5 sm:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="label" for="tax_rule">Pajak</label>
                        <select class="select" id="tax_rule" name="tax_rule">
                            @foreach (['proportional' => 'Proporsional (sesuai porsi)', 'equal' => 'Rata semua peserta', 'none' => 'Tidak dibebankan'] as $value => $label)
                                <option value="{{ $value }}" @selected($bill->tax_rule === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="service_rule">Biaya Servis</label>
                        <select class="select" id="service_rule" name="service_rule">
                            @foreach (['proportional' => 'Proporsional (sesuai porsi)', 'equal' => 'Rata semua peserta', 'none' => 'Tidak dibebankan'] as $value => $label)
                                <option value="{{ $value }}" @selected($bill->service_rule === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="discount_rule">Diskon</label>
                        <select class="select" id="discount_rule" name="discount_rule">
                            @foreach (['proportional' => 'Proporsional (sesuai porsi)', 'equal' => 'Rata semua peserta', 'none' => 'Tidak dibagikan'] as $value => $label)
                                <option value="{{ $value }}" @selected($bill->discount_rule === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="rounding_method">Pembulatan</label>
                        <select class="select" id="rounding_method" name="rounding_method">
                            @foreach (['nearest_100' => 'Ke Rp100 terdekat', 'nearest_500' => 'Ke Rp500 terdekat', 'nearest_1000' => 'Ke Rp1.000 terdekat', 'none' => 'Tanpa pembulatan'] as $value => $label)
                                <option value="{{ $value }}" @selected($bill->rounding_method === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-outline justify-center sm:col-span-2">
                        <i data-lucide="refresh-cw" class="h-4 w-4"></i> Terapkan & Hitung Ulang
                    </button>
                </form>
            </section>
        </div>

        <aside class="space-y-3 lg:sticky lg:top-[76px] lg:self-start">
            <div class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-line px-4 py-3">
                    <h3 class="text-[13px] font-extrabold text-ink">Bagian Tiap Orang</h3>
                    <span class="text-[10px] font-bold text-muted">{{ $bill->participants->count() }} peserta</span>
                </div>

                <div class="max-h-[430px] divide-y divide-line overflow-y-auto scroll-thin">
                    @foreach ($bill->participants as $participant)
                        @php
                            $share = $shares[$participant->id] ?? null;
                        @endphp
                        <div class="p-3.5 {{ $participant->is_current_user ? 'bg-light/25' : '' }}">
                            <div class="flex items-center gap-2.5">
                                <span class="avatar-initial h-8 w-8 text-[10px]" style="background: {{ $participant->color }}">{{ $participant->initials }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[11.5px] font-bold text-ink">{{ $participant->name }} @if ($participant->is_current_user)<span class="text-[9px] font-bold text-primary">(Saya)</span>@endif</p>
                                    <p class="text-[9.5px] text-muted">{{ $share ? count($share['items_list']) : 0 }} item</p>
                                </div>
                                <p class="text-[13px] font-extrabold text-ink hash">{{ format_rupiah($share['final_total'] ?? 0) }}</p>
                            </div>

                            @if ($share)
                                <details class="group mt-1.5">
                                    <summary class="cursor-pointer list-none text-[9.5px] font-bold text-primary hover:underline">Lihat rincian</summary>
                                    <div class="mt-1.5 space-y-1 rounded-xl bg-white p-2.5">
                                        @foreach ($share['items_list'] as $item)
                                            <div class="flex items-start justify-between gap-2 text-[9.5px]">
                                                <span class="min-w-0 flex-1 text-muted"><span class="font-semibold text-ink">{{ $item['name'] }}</span> · {{ $item['portion_text'] }}</span>
                                                <span class="shrink-0 font-bold text-ink hash">{{ format_rupiah($item['price_share']) }}</span>
                                            </div>
                                        @endforeach
                                        <div class="mt-1.5 space-y-0.5 border-t border-dashed border-line pt-1.5 text-[9.5px]">
                                            @if (($share['discount_share'] ?? 0) > 0)
                                                <div class="flex justify-between text-danger"><span>Diskon</span><span class="hash">-{{ format_rupiah($share['discount_share']) }}</span></div>
                                            @endif
                                            @if (($share['tax_share'] ?? 0) > 0)
                                                <div class="flex justify-between text-muted"><span>Pajak</span><span class="hash">{{ format_rupiah($share['tax_share']) }}</span></div>
                                            @endif
                                            @if (($share['service_share'] ?? 0) > 0)
                                                <div class="flex justify-between text-muted"><span>Servis</span><span class="hash">{{ format_rupiah($share['service_share']) }}</span></div>
                                            @endif
                                            @if (($share['other_fees_share'] ?? 0) > 0)
                                                <div class="flex justify-between text-muted"><span>Biaya lain</span><span class="hash">{{ format_rupiah($share['other_fees_share']) }}</span></div>
                                            @endif
                                            @if (($share['rounding_adjustment'] ?? 0) !== 0)
                                                <div class="flex justify-between text-muted"><span>Pembulatan</span><span class="hash">{{ $share['rounding_adjustment'] > 0 ? '+' : '' }}{{ format_rupiah($share['rounding_adjustment']) }}</span></div>
                                            @endif
                                        </div>
                                    </div>
                                </details>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card p-4">
                <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Rekening Tujuan</p>
                @if ($defaultBank)
                    <p class="mt-1.5 text-[12px] font-extrabold text-ink">{{ $defaultBank->bank }} · {{ $defaultBank->account_number }}</p>
                    <p class="text-[10.5px] text-muted">a.n. {{ $defaultBank->account_holder }}</p>
                @else
                    <p class="mt-1.5 text-[11px] text-muted">Belum ada rekening. Tambahkan di halaman profil.</p>
                @endif

                <form method="POST" action="{{ route('split-bills.confirm', $bill) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full justify-center py-3" @disabled(count($result['unallocated_item_ids']) > 0 || ! $result['is_matched'])>
                        <i data-lucide="check-check" class="h-4 w-4"></i> Konfirmasi & Buat Tagihan
                    </button>
                </form>
                <p class="mt-2 text-center text-[9.5px] leading-relaxed text-muted">
                    Setelah dikonfirmasi, link tagihan tiap peserta bisa dibagikan lewat WhatsApp.
                </p>
            </div>

            <a href="{{ route('split-bills.allocation', $bill) }}" class="btn btn-ghost w-full justify-center">
                <i data-lucide="arrow-left" class="h-4 w-4"></i> Ubah Pembagian
            </a>
        </aside>
    </div>
</div>
@endsection
