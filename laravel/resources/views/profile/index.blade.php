@extends('layouts.app')

@section('title', 'Profil & Rekening')

@section('content')
@php
    $billCount = $user->splitBills()->count();
    $poolCount = $user->iuranPools()->count();
@endphp

<div class="space-y-4">
    <section class="relative overflow-hidden rounded-[24px] border border-line bg-white p-4 sm:p-5">
        <div class="absolute -right-16 -top-20 h-52 w-52 rounded-full bg-light/70 blur-2xl"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="relative shrink-0">
                <img src="{{ $user->avatar }}" alt="{{ $user->name }}" class="h-16 w-16 rounded-2xl object-cover ring-4 ring-light">
                <span class="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-primary text-white"><i data-lucide="check" class="h-3 w-3"></i></span>
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="text-[20px] font-extrabold leading-tight tracking-tight text-ink">{{ $user->name }}</h2>
                <p class="mt-0.5 text-[11.5px] font-medium text-muted">{{ $user->email }}</p>
                <p class="mt-1 text-[10px] text-muted">Bergabung {{ $user->created_at?->translatedFormat('F Y') }} · {{ $user->phone ?: 'Nomor belum diisi' }}</p>
            </div>
            <div class="grid grid-cols-3 gap-2 sm:min-w-[280px]">
                <div class="rounded-xl bg-[#f8faf9] px-2.5 py-2 text-center">
                    <p class="text-[15px] font-extrabold text-ink">{{ $billCount }}</p>
                    <p class="text-[9px] font-semibold text-muted">Split Bill</p>
                </div>
                <div class="rounded-xl bg-[#f8faf9] px-2.5 py-2 text-center">
                    <p class="text-[15px] font-extrabold text-ink">{{ $poolCount }}</p>
                    <p class="text-[9px] font-semibold text-muted">Iuran</p>
                </div>
                <div class="rounded-xl bg-[#f8faf9] px-2.5 py-2 text-center">
                    <p class="text-[15px] font-extrabold text-ink">{{ $user->bankAccounts->count() }}</p>
                    <p class="text-[9px] font-semibold text-muted">Rekening</p>
                </div>
            </div>
        </div>
    </section>

    @if ($errors->any())
        <div class="rounded-xl border border-danger/20 bg-danger/5 px-3.5 py-2.5 text-[11.5px] font-semibold text-danger">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[1.35fr_1fr]">
        <div class="space-y-4">
            <section class="card p-4 sm:p-5">
                <div>
                    <h3 class="text-[14px] font-extrabold tracking-tight text-ink">Data Profil</h3>
                    <p class="mt-0.5 text-[10.5px] text-muted">Nama ini akan muncul sebagai pembuat tagihan.</p>
                </div>

                <form method="POST" action="{{ route('profile.update') }}" class="mt-4 space-y-3" x-data="{ avatar: @js($user->avatar_url) }">
                    @csrf
                    @method('PUT')

                    <div class="flex items-center gap-3 rounded-2xl border border-line p-3">
                        <img :src="avatar || @js($user->avatar)" alt="Avatar" class="h-12 w-12 rounded-xl object-cover">
                        <div class="min-w-0 flex-1">
                            <label class="label" for="avatar_url">URL Foto Profil</label>
                            <input class="input input-sm" type="url" id="avatar_url" name="avatar_url" x-model="avatar" value="{{ old('avatar_url', $user->avatar_url) }}" placeholder="https://...">
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label" for="name">Nama Lengkap</label>
                            <input class="input" type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div>
                            <label class="label" for="email">Email</label>
                            <input class="input" type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label" for="phone">Nomor WhatsApp</label>
                            <input class="input" type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="08xxxxxxxxxx">
                        </div>
                    </div>

                    <div class="border-t border-line pt-3">
                        <h4 class="text-[12px] font-extrabold text-ink">Preferensi Hitung</h4>
                        <p class="mt-0.5 text-[10px] text-muted">Nilai awal saat kamu membuat split bill baru.</p>

                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="label" for="preferred_currency">Mata Uang</label>
                                <select class="select" id="preferred_currency" name="preferred_currency">
                                    <option value="IDR" @selected($user->preferred_currency === 'IDR')>IDR - Rupiah Indonesia</option>
                                    <option value="USD" @selected($user->preferred_currency === 'USD')>USD - US Dollar</option>
                                    <option value="SGD" @selected($user->preferred_currency === 'SGD')>SGD - Singapore Dollar</option>
                                </select>
                            </div>
                            <div>
                                <label class="label" for="default_rounding">Pembulatan</label>
                                <select class="select" id="default_rounding" name="default_rounding">
                                    @foreach (['nearest_100' => 'Ke Rp100 terdekat', 'nearest_500' => 'Ke Rp500 terdekat', 'nearest_1000' => 'Ke Rp1.000 terdekat', 'none' => 'Tanpa pembulatan'] as $value => $label)
                                        <option value="{{ $value }}" @selected($user->default_rounding === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-full justify-center sm:w-auto">
                        <i data-lucide="save" class="h-4 w-4"></i> Simpan Profil
                    </button>
                </form>
            </section>
        </div>

        <div class="space-y-4">
            <section class="card p-4 sm:p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-[14px] font-extrabold tracking-tight text-ink">Rekening & E-Wallet</h3>
                        <p class="mt-0.5 text-[10.5px] text-muted">Tujuan transfer yang tampil di tagihan.</p>
                    </div>
                    <span class="badge badge-neutral">{{ $user->bankAccounts->count() }}</span>
                </div>

                <div class="mt-3 space-y-2">
                    @forelse ($user->bankAccounts as $account)
                        <div class="rounded-2xl border p-3 {{ $account->is_default ? 'border-primary/40 bg-light/20' : 'border-line' }}">
                            <div class="flex items-start gap-2.5">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-primary-dark shadow-sm"><i data-lucide="landmark" class="h-4 w-4"></i></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <p class="truncate text-[11.5px] font-extrabold text-ink">{{ $account->bank }}</p>
                                        @if ($account->is_default)
                                            <span class="badge badge-success">Utama</span>
                                        @endif
                                    </div>
                                    <p class="text-[11px] font-bold text-primary-dark">{{ $account->account_number }}</p>
                                    <p class="text-[9.5px] text-muted">a.n. {{ $account->account_holder }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-0.5">
                                    <button type="button" class="icon-btn h-7 w-7" data-copy="{{ $account->account_number }}" data-copy-message="Nomor rekening disalin." title="Salin">
                                        <i data-lucide="copy" class="h-3.5 w-3.5"></i>
                                    </button>
                                    @if (! $account->is_default)
                                        <form method="POST" action="{{ route('profile.banks.default', $account) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="icon-btn h-7 w-7" title="Jadikan utama"><i data-lucide="star" class="h-3.5 w-3.5"></i></button>
                                        </form>
                                    @endif
                                    <button type="button" class="icon-btn h-7 w-7 text-danger" title="Hapus rekening" aria-label="Hapus rekening"
                                            data-confirm-delete
                                            data-confirm-title="Hapus rekening?"
                                            data-confirm-message="{{ $account->bank }} · {{ $account->account_number }}"
                                            data-confirm-url="{{ route('profile.banks.destroy', $account) }}"
                                            data-confirm-method="DELETE"
                                            data-confirm-label="Hapus">
                                        <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                    </button>
                                </div>
                            </div>

                            <details class="group mt-1.5">
                                <summary class="list-none cursor-pointer text-[9.5px] font-bold text-primary hover:underline">Edit rekening</summary>
                                <form method="POST" action="{{ route('profile.banks.update', $account) }}" class="mt-2 grid gap-2">
                                    @csrf
                                    @method('PUT')
                                    <input class="input input-sm" type="text" name="bank" value="{{ $account->bank }}" aria-label="Nama bank / e-wallet" required>
                                    <input class="input input-sm" type="text" name="account_number" value="{{ $account->account_number }}" aria-label="Nomor rekening" required>
                                    <input class="input input-sm" type="text" name="account_holder" value="{{ $account->account_holder }}" aria-label="Atas nama" required>
                                    <button type="submit" class="btn btn-outline btn-sm justify-center">Simpan Perubahan</button>
                                </form>
                            </details>
                        </div>
                    @empty
                        <p class="rounded-xl border border-dashed border-line px-3 py-5 text-center text-[10.5px] text-muted">Belum ada rekening. Tambahkan di bawah.</p>
                    @endforelse
                </div>

                <details class="mt-3 rounded-2xl border border-dashed border-line p-2.5">
                    <summary class="flex cursor-pointer list-none items-center justify-center gap-1.5 text-[10.5px] font-bold text-primary">
                        <i data-lucide="plus" class="h-3.5 w-3.5"></i> Tambah rekening baru
                    </summary>
                    <form method="POST" action="{{ route('profile.banks.store') }}" class="mt-2 space-y-2">
                        @csrf
                        <input class="input input-sm" type="text" name="bank" placeholder="Nama bank / e-wallet (BCA, GoPay...)" aria-label="Nama bank / e-wallet" required>
                        <input class="input input-sm" type="text" name="account_number" placeholder="Nomor rekening / nomor HP" aria-label="Nomor rekening" required>
                        <input class="input input-sm" type="text" name="account_holder" placeholder="Atas nama" value="{{ $user->name }}" aria-label="Atas nama" required>
                        <label class="flex items-center gap-2 text-[10px] font-semibold text-muted">
                            <input type="checkbox" name="is_default" value="1" class="rounded border-line text-primary focus:ring-primary"> Jadikan rekening utama
                        </label>
                        <button type="submit" class="btn btn-primary btn-sm w-full justify-center">Tambah Rekening</button>
                    </form>
                </details>
            </section>

            <section class="card p-4">
                <h3 class="text-[12.5px] font-extrabold text-ink">Sesi Akun</h3>
                <p class="mt-0.5 text-[10px] text-muted">Masuk sebagai {{ $user->email }}</p>
                <div class="mt-3 flex gap-2">
                    <a href="{{ route('notifications.index') }}" class="btn btn-ghost btn-sm flex-1 justify-center">
                        <i data-lucide="bell" class="h-3.5 w-3.5"></i> Notifikasi
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="flex-1">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm w-full justify-center text-danger">
                            <i data-lucide="log-out" class="h-3.5 w-3.5"></i> Keluar
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
