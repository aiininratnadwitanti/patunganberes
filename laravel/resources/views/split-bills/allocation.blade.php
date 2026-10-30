@extends('layouts.app')

@section('title', 'Bagi Item')

@section('content')
@php
    $wizardConfig = [
        'items' => $bill->items->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'total_price' => $item->total_price,
        ])->values()->all(),
        'participants' => $bill->participants->map(fn ($participant) => [
            'id' => $participant->id,
            'name' => $participant->name,
            'initials' => $participant->initials,
            'color' => $participant->color,
        ])->values()->all(),
        'allocations' => $initialAllocations,
        'tax' => $bill->is_tax_included ? 0 : $bill->tax_amount,
        'service' => $bill->service_charge_amount,
        'discount' => $bill->discount_amount,
        'other' => $bill->other_fees_amount,
        'taxRule' => $bill->tax_rule,
        'serviceRule' => $bill->service_rule,
        'discountRule' => $bill->discount_rule,
        'rounding' => $bill->rounding_method,
    ];
@endphp

@include('partials.wizard-steps', ['current' => 3, 'bill' => $bill])

<div x-data="allocationWizard({{ json_encode($wizardConfig, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }})">
    <div class="grid gap-4 lg:grid-cols-[1.6fr_1fr]">
        <form method="POST" action="{{ route('split-bills.allocation.update', $bill) }}" class="space-y-3">
            @csrf
            @method('PUT')
            <input type="hidden" name="allocations" :value="payload()">

            <div class="card flex flex-col gap-2.5 p-3.5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[12px] font-extrabold text-ink"><span x-text="completedCount()"></span> dari <span x-text="items.length"></span> item sudah dibagi</p>
                    <p class="mt-0.5 text-[10.5px] font-semibold text-primary-dark">Semua item otomatis dibagi rata ke semua peserta — ubah hanya yang perlu.</p>
                    <p class="mt-0.5 text-[10.5px] text-muted">Item yang belum dibagi tidak akan masuk tagihan siapa pun.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-ghost btn-sm" @click="applyEqualAll()">
                        <i data-lucide="users" class="h-3.5 w-3.5"></i> Bagi Rata Semua
                    </button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="resetAll()">
                        <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i> Reset
                    </button>
                </div>
            </div>

            <div class="space-y-2.5">
                <template x-for="(item, index) in items" :key="item.id">
                    <section class="card overflow-hidden">
                        <div class="flex items-start gap-3 border-b border-line p-3.5">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#f1f5f2] text-[10px] font-extrabold text-muted" x-text="index + 1"></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-[12.5px] font-extrabold text-ink" x-text="item.name"></p>
                                        <p class="mt-0.5 text-[10.5px] font-medium text-muted">
                                            <span x-text="item.quantity"></span>x <span x-text="money(item.unit_price)"></span>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[12.5px] font-extrabold text-ink hash" x-text="money(item.total_price)"></p>
                                        <span class="badge mt-1" :class="isAllocated(item) ? 'badge-success' : 'badge-warning'" x-text="isAllocated(item) ? 'Terbagi' : 'Belum dibagi'"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-3.5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="segment">
                                    <button type="button" :class="allocations[item.id].type === 'single' ? 'is-active' : ''" @click="setType(item, 'single')">
                                        <i data-lucide="user" class="h-3 w-3"></i> Satu orang
                                    </button>
                                    <button type="button" :class="allocations[item.id].type === 'shared_equal' ? 'is-active' : ''" @click="setType(item, 'shared_equal')">
                                        <i data-lucide="users" class="h-3 w-3"></i> Bagi rata
                                    </button>
                                    <button type="button" :class="allocations[item.id].type === 'by_portion' ? 'is-active' : ''" @click="setType(item, 'by_portion')">
                                        <i data-lucide="pie-chart" class="h-3 w-3"></i> Per porsi
                                    </button>
                                </div>

                                <p class="text-[10px] font-semibold text-muted" x-show="allocations[item.id].type === 'shared_equal' && allocations[item.id].shared.length > 0">
                                    <span x-text="allocations[item.id].shared.length"></span> orang · <span class="font-extrabold text-ink" x-text="money(sharePreview(item))"></span>/orang
                                </p>
                                <p class="text-[10px] font-semibold text-muted" x-show="allocations[item.id].type === 'by_portion'">
                                    <span class="font-extrabold text-ink" x-text="assignedQty(item)"></span>/<span x-text="item.quantity"></span> porsi terbagi
                                </p>
                            </div>

                            <div class="mt-3" x-show="allocations[item.id].type === 'single'">
                                <label class="text-[9.5px] font-bold uppercase tracking-wide text-muted">Ditanggung oleh</label>
                                <select class="input input-sm mt-1" @change="setSingle(item, Number($event.target.value))">
                                    <template x-for="participant in participants" :key="'single-' + item.id + '-' + participant.id">
                                        <option :value="participant.id" :selected="allocations[item.id].single === participant.id" x-text="participant.name || 'Tanpa nama'"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="mt-3" x-show="allocations[item.id].type === 'shared_equal'">
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="participant in participants" :key="'shared-' + item.id + '-' + participant.id">
                                        <button type="button" class="chip" :class="isShared(item, participant.id) ? 'is-selected' : ''" @click="toggleShared(item, participant.id)">
                                            <span class="avatar-initial h-5 w-5 text-[8px]" :style="`background: ${participant.color}`" x-text="participant.initials"></span>
                                            <span x-text="participant.name"></span>
                                            <i data-lucide="check" class="h-3 w-3" x-show="isShared(item, participant.id)"></i>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            <div class="mt-2 space-y-1.5" x-show="allocations[item.id].type === 'by_portion'">
                                <template x-for="participant in participants" :key="'portion-' + item.id + '-' + participant.id">
                                    <div class="flex items-center gap-2 rounded-xl border border-line px-2.5 py-1.5">
                                        <span class="avatar-initial h-6 w-6 text-[8px]" :style="`background: ${participant.color}`" x-text="participant.initials"></span>
                                        <span class="min-w-0 flex-1 truncate text-[11px] font-semibold text-ink" x-text="participant.name"></span>
                                        <span class="hidden text-[10px] font-medium text-muted sm:block" x-text="money(item.unit_price * portion(item, participant.id))"></span>
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" class="qty-btn" @click="changePortion(item, participant.id, -1)">-</button>
                                            <span class="w-5 text-center text-[11px] font-extrabold text-ink" x-text="portion(item, participant.id)"></span>
                                            <button type="button" class="qty-btn" @click="changePortion(item, participant.id, 1)">+</button>
                                        </div>
                                    </div>
                                </template>
                                <p class="text-[9.5px] leading-relaxed text-muted" x-show="assignedQty(item) < item.quantity">
                                    Masih ada <span class="font-bold text-warning" x-text="item.quantity - assignedQty(item)"></span> porsi yang belum dibagi.
                                </p>
                            </div>
                        </div>
                    </section>
                </template>
            </div>

            <div class="flex items-center justify-between gap-2 pt-1">
                <a href="{{ route('split-bills.participants', $bill) }}" class="btn btn-ghost">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i> Kembali
                </a>
                <button type="submit" class="btn btn-primary">
                    Lihat Ringkasan <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </button>
            </div>
        </form>

        <aside class="lg:sticky lg:top-[76px] lg:self-start">
            <div class="card p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-primary-dark">Progress Pembagian</p>
                        <p class="mt-1 text-[26px] font-extrabold tracking-tight text-ink"><span x-text="completedCount()"></span><span class="text-[16px] text-muted">/<span x-text="items.length"></span></span></p>
                    </div>
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-light text-primary-dark"><i data-lucide="pie-chart" class="h-5 w-5"></i></span>
                </div>

                <div class="progress-track mt-2">
                    <div class="progress-fill" :style="`width: ${items.length ? Math.round(completedCount() / items.length * 100) : 0}%`"></div>
                </div>

                <div class="mt-4 rounded-xl bg-[#f8faf9] p-3">
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Perkiraan per orang</p>
                        <span class="text-[9px] font-semibold text-muted">termasuk pajak/servis</span>
                    </div>
                    <div class="mt-2 space-y-1.5">
                        <template x-for="share in perPersonTotals()" :key="'share-' + share.participant.id">
                            <div class="flex items-center gap-2">
                                <span class="avatar-initial h-5 w-5 text-[8px]" :style="`background: ${share.participant.color}`" x-text="share.participant.initials"></span>
                                <span class="min-w-0 flex-1 truncate text-[10.5px] font-semibold text-ink" x-text="share.participant.name"></span>
                                <span class="text-[10.5px] font-extrabold text-ink hash" x-text="money(share.final_total)"></span>
                            </div>
                        </template>
                    </div>
                    <div class="mt-2 flex items-center justify-between border-t border-line pt-2">
                        <span class="text-[10px] font-bold text-muted">Total terbagi</span>
                        <span class="text-[10.5px] font-extrabold text-ink hash" x-text="money(allocatedTotal())"></span>
                    </div>
                </div>
            </div>

            <div class="card mt-3 p-4">
                <p class="text-[10.5px] font-bold uppercase tracking-[0.1em] text-muted">Cara membagi</p>
                <div class="mt-2.5 space-y-2">
                    <div class="flex gap-2">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-light text-primary-dark"><i data-lucide="user" class="h-3 w-3"></i></span>
                        <p class="text-[10px] leading-relaxed text-muted"><span class="font-bold text-ink">Satu orang</span> untuk menu yang jelas pemesannya.</p>
                    </div>
                    <div class="flex gap-2">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-light text-primary-dark"><i data-lucide="users" class="h-3 w-3"></i></span>
                        <p class="text-[10px] leading-relaxed text-muted"><span class="font-bold text-ink">Bagi rata</span> untuk menu yang dinikmati bersama.</p>
                    </div>
                    <div class="flex gap-2">
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-light text-primary-dark"><i data-lucide="pie-chart" class="h-3 w-3"></i></span>
                        <p class="text-[10px] leading-relaxed text-muted"><span class="font-bold text-ink">Per porsi</span> jika satu menu dipesan lebih dari satu porsi.</p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
