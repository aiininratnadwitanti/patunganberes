@extends('layouts.app')

@section('title', 'Pilih Peserta')

@section('content')
@php
    $participantsConfig = [
        'defaultName' => $navUser->name,
        'participants' => $bill->participants->map(fn ($participant) => [
            'id' => $participant->id,
            'name' => $participant->name,
            'is_current_user' => $participant->is_current_user,
            'color' => $participant->color,
            'initials' => $participant->initials,
        ])->values()->all(),
    ];
@endphp

@include('partials.wizard-steps', ['current' => 2, 'bill' => $bill])

<div x-data="participantsEditor({{ json_encode($participantsConfig, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }})">
    @if ($errors->any())
        <div class="mb-3 rounded-xl border border-danger/20 bg-danger/5 px-3.5 py-2.5 text-[11.5px] font-semibold text-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
        <form method="POST" action="{{ route('split-bills.participants.update', $bill) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <section class="card p-4 sm:p-5">
                <h2 class="text-[15px] font-extrabold tracking-tight text-ink">Nama Split Bill</h2>
                <p class="mt-0.5 text-[11px] text-muted">Beri nama yang mudah dikenali teman-temanmu.</p>

                <div class="mt-3 grid gap-3 sm:grid-cols-[1.4fr_1fr]">
                    <div>
                        <label class="label" for="title">Judul</label>
                        <input class="input" type="text" id="title" name="title" value="{{ old('title', $bill->title) }}" required>
                    </div>
                    <div>
                        <label class="label" for="date">Tanggal</label>
                        <input class="input" type="date" id="date" name="date" value="{{ old('date', $bill->date?->format('Y-m-d')) }}" required>
                    </div>
                </div>
            </section>

            <section class="card p-4 sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-[15px] font-extrabold tracking-tight text-ink">Daftar Peserta</h2>
                        <p class="mt-0.5 text-[11px] text-muted">Namamu diambil otomatis dari profil. Sisanya adalah teman yang ikut patungan.</p>
                    </div>
                    <span class="badge badge-success"><span x-text="participants.length"></span> orang</span>
                </div>

                <div class="mt-4 space-y-2">
                    <template x-for="(participant, index) in participants" :key="participant.uid">
                        <div class="flex items-center gap-2.5 rounded-2xl border border-line p-2.5 transition"
                             :class="participant.is_current_user ? 'border-primary/40 bg-light/30' : ''">
                            <span class="avatar-initial h-9 w-9 text-[11px]" :style="`background: ${participant.color || '#16A34A'}`" x-text="initials(participant.name)"></span>

                            <div class="min-w-0 flex-1">
                                <input type="hidden" :name="'participants[' + index + '][id]'" :value="participant.id">
                                <input type="hidden" :name="'participants[' + index + '][is_current_user]'" :value="participant.is_current_user ? 1 : 0">
                                <input type="text" class="input input-sm" :name="'participants[' + index + '][name]'" x-model="participant.name" placeholder="Nama peserta" required>
                            </div>

                            <button type="button" class="icon-btn shrink-0 text-danger hover:bg-danger/10" @click="remove(index)" title="Hapus peserta">
                                <i data-lucide="x" class="h-4 w-4"></i>
                            </button>
                        </div>
                    </template>
                </div>

                <div class="mt-3 rounded-2xl border border-dashed border-line p-2.5">
                    <label class="mb-1.5 block text-[9.5px] font-bold uppercase tracking-[0.1em] text-muted">Tambah peserta baru</label>
                    <div class="flex gap-2">
                        <input type="text" class="input" x-model="newName" @keydown.enter.prevent="add()" placeholder="Nama teman...">
                        <button type="button" class="btn btn-primary shrink-0" @click="add()">
                            <i data-lucide="user-plus" class="h-4 w-4"></i> Tambah
                        </button>
                    </div>
                </div>
            </section>

            <div class="flex items-center justify-between gap-2">
                <a href="{{ route('split-bills.validate', $bill) }}" class="btn btn-ghost">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i> Kembali
                </a>
                <button type="submit" class="btn btn-primary">
                    Lanjut Bagi Item <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </button>
            </div>
        </form>

        <aside class="lg:sticky lg:top-[76px] lg:self-start">
            <div class="card overflow-hidden">
                <div class="border-b border-line bg-gradient-to-br from-light/70 to-white p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-primary-dark">Total Tagihan</p>
                    <p class="mt-1 text-[26px] font-extrabold tracking-tight text-ink hash">{{ format_rupiah($bill->grand_total) }}</p>
                    <p class="text-[10.5px] font-medium text-muted">{{ $bill->merchant_name ?: $bill->title }}</p>
                </div>

                <div class="p-4">
                    <div class="flex items-center justify-between text-[11.5px]">
                        <span class="font-medium text-muted">Jumlah peserta</span>
                        <span class="font-extrabold text-ink"><span x-text="participants.length"></span> orang</span>
                    </div>
                    <div class="mt-2 flex items-center justify-between text-[11.5px]">
                        <span class="font-medium text-muted">Rata-rata per orang</span>
                        <span class="font-extrabold text-ink hash" x-text="'Rp' + Math.round({{ $bill->grand_total }} / Math.max(participants.length, 1)).toLocaleString('id-ID')"></span>
                    </div>
                    <p class="mt-2 text-[10px] leading-relaxed text-muted">Angka rata-rata hanya perkiraan. Pembagian final menyesuaikan item yang dipilih tiap orang.</p>

                    <div class="mt-4 rounded-xl bg-[#f8faf9] p-3">
                        <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Peserta saat ini</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <template x-for="participant in participants" :key="'chip-' + participant.uid">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-2 py-1 text-[10px] font-semibold text-ink shadow-sm">
                                    <span class="h-1.5 w-1.5 rounded-full" :style="`background: ${participant.color || '#16A34A'}`"></span>
                                    <span x-text="participant.name || 'Tanpa nama'"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-3 p-4">
                <div class="flex gap-2.5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-light text-primary-dark"><i data-lucide="lightbulb" class="h-4 w-4"></i></span>
                    <div>
                        <p class="text-[11px] font-bold text-ink">Tips pembagian</p>
                        <p class="mt-0.5 text-[10px] leading-relaxed text-muted">Peserta yang tidak ikut makan tetap bisa dimasukkan untuk biaya servis atau pajak bersama.</p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
