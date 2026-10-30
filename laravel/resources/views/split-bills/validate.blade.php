@extends('layouts.app')

@section('title', 'Periksa Detail Struk')

@section('content')
@php
    $editorConfig = [
        'items' => $bill->items->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'total_price' => $item->total_price,
        ])->values()->all(),
        'receiptTotal' => $bill->grand_total,
        'tax' => $bill->tax_amount,
        'service' => $bill->service_charge_amount,
        'discount' => $bill->discount_amount,
        'other' => $bill->other_fees_amount,
        'taxIncluded' => $bill->is_tax_included,
    ];

    $source = match ($bill->scanner_source) {
        'gemini_ai' => ['Gemini AI', 'badge-success', 'sparkles'],
        'demo' => ['Contoh Struk', 'badge-info', 'flask-conical'],
        default => ['Input Manual', 'badge-neutral', 'pen-line'],
    };
@endphp

@include('partials.wizard-steps', ['current' => 1, 'bill' => $bill])

<div x-data="receiptEditor({{ json_encode($editorConfig, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }})">
    @if ($errors->any())
        <div class="mb-3 rounded-xl border border-danger/20 bg-danger/5 px-3.5 py-2.5 text-[11.5px] font-semibold text-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
        <form id="validate-form" method="POST" action="{{ route('split-bills.validate.update', $bill) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <section class="card p-4 sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-[15px] font-extrabold tracking-tight text-ink">Informasi Struk</h2>
                        <p class="mt-0.5 text-[11px] text-muted">Data ini akan tampil di halaman tagihan temanmu.</p>
                    </div>
                    <span class="badge {{ $source[1] }}"><i data-lucide="{{ $source[2] }}" class="h-3 w-3"></i> {{ $source[0] }}</span>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label" for="merchant_name">Nama Merchant / Toko</label>
                        <input class="input" type="text" id="merchant_name" name="merchant_name" value="{{ old('merchant_name', $bill->merchant_name) }}" placeholder="Contoh: Kedai Nusantara" required>
                    </div>
                    <div>
                        <label class="label" for="date">Tanggal</label>
                        <input class="input" type="date" id="date" name="date" value="{{ old('date', $bill->date?->format('Y-m-d')) }}" required>
                    </div>
                    <div>
                        <label class="label" for="time">Jam</label>
                        <input class="input" type="time" id="time" name="time" value="{{ old('time', $bill->time) }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="category">Kategori</label>
                        <select class="select" id="category" name="category">
                            @foreach (['restoran' => 'Restoran', 'kafe' => 'Kafe & Nongkrong', 'makan_bersama' => 'Makan Bersama', 'belanja' => 'Belanja', 'hadiah' => 'Hadiah & Acara'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('category', $bill->category) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            <section class="card p-4 sm:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-[15px] font-extrabold tracking-tight text-ink">Item Pesanan</h2>
                        <p class="mt-0.5 text-[11px] text-muted">Hapus atau koreksi item yang tidak sesuai struk.</p>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" @click="addItem()">
                        <i data-lucide="plus" class="h-3.5 w-3.5"></i> Tambah
                    </button>
                </div>

                <div x-cloak x-show="itemIssueCount() > 0 || hasTotalWarning()" class="mt-3 rounded-xl border border-warning/30 bg-warning/5 p-3">
                    <p class="text-[11.5px] font-extrabold text-ink"><span x-text="itemIssueCount()"></span> item perlu diperiksa</p>
                    <p class="mt-1 text-[10.5px] leading-relaxed text-muted">Angka hasil scan bisa keliru (mis. &quot;4.000&quot; terbaca 4). Koreksi item bertanda kuning sebelum lanjut.</p>
                    <p x-show="hasTotalWarning()" class="mt-1.5 text-[10.5px] font-semibold text-danger">
                        Total item + pajak/servis berbeda <span x-text="money(totalDifference())"></span> dari total struk (<span x-text="money(receiptTotal)"></span>).
                    </p>
                </div>

                <div class="mt-3 space-y-2">
                    <template x-for="(item, index) in items" :key="item.uid">
                        <div class="rounded-2xl border p-3" :class="itemIssues(item).length ? 'border-warning/50 bg-warning/5' : 'border-line'">
                            <div class="flex items-start gap-2">
                                <span class="mt-2 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#f1f5f2] text-[9px] font-extrabold text-muted" x-text="index + 1"></span>
                                <div class="min-w-0 flex-1">
                                    <input type="hidden" :name="'items[' + index + '][id]'" :value="item.id">
                                    <input type="text" class="input" :name="'items[' + index + '][name]'" x-model="item.name" placeholder="Nama item" required>
                                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-[90px_1fr_1fr]">
                                        <div>
                                            <label class="text-[9.5px] font-bold uppercase tracking-wide text-muted">Qty</label>
                                            <input type="number" min="1" class="input input-sm" :name="'items[' + index + '][quantity]'" x-model.number="item.quantity" @input="forgetScannedTotal(item)" required>
                                        </div>
                                        <div>
                                            <label class="text-[9.5px] font-bold uppercase tracking-wide text-muted">Harga Satuan</label>
                                            <input type="number" min="0" step="100" class="input input-sm" :name="'items[' + index + '][unit_price]'" x-model.number="item.unit_price" @input="forgetScannedTotal(item)" required>
                                        </div>
                                        <div class="col-span-2 sm:col-span-1">
                                            <label class="text-[9.5px] font-bold uppercase tracking-wide text-muted">Total</label>
                                            <div class="flex h-[31px] items-center justify-end rounded-lg bg-[#f8faf9] px-2 text-[11.5px] font-extrabold text-ink hash" x-text="money(lineTotal(item))"></div>
                                        </div>
                                    </div>
                                    <div class="mt-2 space-y-1" x-show="itemIssues(item).length > 0">
                                        <template x-for="(issue, issueIndex) in itemIssues(item)" :key="'issue-' + item.uid + '-' + issueIndex">
                                            <p class="text-[10px] font-semibold leading-relaxed text-warning" x-text="'\u2022 ' + issue"></p>
                                        </template>
                                    </div>
                                </div>
                                <button type="button" class="icon-btn mt-1 shrink-0 text-danger hover:bg-danger/10" @click="removeItem(index)" title="Hapus item">
                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <button type="button" class="mt-3 flex w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-line py-2 text-[11px] font-bold text-muted transition hover:border-primary/40 hover:text-primary" @click="addItem()">
                    <i data-lucide="plus" class="h-3.5 w-3.5"></i> Tambah item lain
                </button>
            </section>

            <section class="card p-4 sm:p-5">
                <h2 class="text-[15px] font-extrabold tracking-tight text-ink">Pajak, Servis & Diskon</h2>
                <p class="mt-0.5 text-[11px] text-muted">Kosongkan jika tidak ada di struk.</p>

                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div>
                        <label class="label" for="tax_amount">Pajak (Rp)</label>
                        <input class="input" type="number" min="0" step="100" id="tax_amount" name="tax_amount" x-model.number="tax">
                    </div>
                    <div>
                        <label class="label" for="service_charge_amount">Servis (Rp)</label>
                        <input class="input" type="number" min="0" step="100" id="service_charge_amount" name="service_charge_amount" x-model.number="service">
                    </div>
                    <div>
                        <label class="label" for="discount_amount">Diskon (Rp)</label>
                        <input class="input" type="number" min="0" step="100" id="discount_amount" name="discount_amount" x-model.number="discount">
                    </div>
                    <div>
                        <label class="label" for="other_fees_amount">Biaya Lain (Rp)</label>
                        <input class="input" type="number" min="0" step="100" id="other_fees_amount" name="other_fees_amount" x-model.number="other">
                    </div>
                </div>

                <label class="mt-3 flex items-start gap-2.5 rounded-xl border border-line bg-[#f8faf9] p-3">
                    <input type="checkbox" name="is_tax_included" value="1" class="mt-0.5 rounded border-line text-primary focus:ring-primary" x-model="taxIncluded">
                    <span>
                        <span class="block text-[11.5px] font-bold text-ink">Pajak sudah termasuk harga</span>
                        <span class="block text-[10px] leading-relaxed text-muted">Centang jika struk mencantumkan "harga sudah termasuk pajak" sehingga pajak tidak ditambahkan lagi.</span>
                    </span>
                </label>
            </section>

            <div class="flex items-center justify-between gap-2">
                <a href="{{ route('split-bills.create') }}" class="btn btn-ghost">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i> Kembali
                </a>
                <button type="submit" class="btn btn-primary">
                    Simpan & Pilih Peserta <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </button>
            </div>
        </form>

        <aside class="lg:sticky lg:top-[76px] lg:self-start">
            <div class="card overflow-hidden">
                <div class="border-b border-line bg-gradient-to-br from-light/70 to-white p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-primary-dark">Ringkasan Struk</p>
                    <p class="mt-1 truncate text-[14px] font-extrabold text-ink">{{ $bill->merchant_name ?: 'Merchant belum diisi' }}</p>
                    <p class="text-[10.5px] font-medium text-muted">{{ $bill->code }} · {{ count($bill->items) }} item</p>
                </div>

                <div class="p-4">
                    <dl class="space-y-2.5 text-[11.5px]">
                        <div class="flex items-center justify-between">
                            <dt class="font-medium text-muted">Subtotal (<span x-text="items.length"></span> item)</dt>
                            <dd class="font-bold text-ink hash" x-text="money(subtotal())"></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="font-medium text-muted">Pajak</dt>
                            <dd class="font-bold text-ink hash" x-text="money(taxIncluded ? 0 : tax)"></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="font-medium text-muted">Servis</dt>
                            <dd class="font-bold text-ink hash" x-text="money(service)"></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="font-medium text-muted">Biaya lain</dt>
                            <dd class="font-bold text-ink hash" x-text="money(other)"></dd>
                        </div>
                        <div class="flex items-center justify-between text-danger">
                            <dt class="font-medium">Diskon</dt>
                            <dd class="font-bold hash" x-text="'-' + money(discount)"></dd>
                        </div>
                    </dl>

                    <div class="mt-3 flex items-end justify-between border-t border-dashed border-line pt-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-muted">Total Struk</p>
                            <p class="text-[9.5px] text-muted" x-cloak x-show="taxIncluded">Pajak sudah termasuk</p>
                        </div>
                        <p class="text-[20px] font-extrabold tracking-tight text-primary-dark hash" x-text="money(grandTotal())"></p>
                    </div>

                    <div class="mt-4 rounded-xl bg-[#f8faf9] p-3">
                        <div class="flex items-start gap-2">
                            <i data-lucide="info" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary"></i>
                            <p class="text-[10px] leading-relaxed text-muted">
                                Total akan dibulatkan sesuai pengaturan profil saat pembagian. Kamu masih bisa mengubah aturan pajak, servis, dan diskon di langkah ringkasan.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" form="validate-form" name="save_mode" value="draft" class="btn btn-ghost mt-3 w-full justify-center">
                <i data-lucide="save" class="h-4 w-4"></i> Simpan sebagai draft & keluar
            </button>
        </aside>
    </div>
</div>
@endsection
