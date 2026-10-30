@extends('layouts.guest')

@section('title', 'Daftar')

@section('content')
<div class="mx-auto flex min-h-screen w-full max-w-5xl items-center justify-center px-4 py-8 sm:px-6">
    <div class="grid w-full overflow-hidden rounded-[28px] border border-line bg-white shadow-[0_30px_80px_-50px_rgba(23,35,28,0.45)] lg:grid-cols-[1fr_1.05fr]">
        <div class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-[#14532d] via-[#166534] to-[#16a34a] p-10 text-white lg:flex">
            <div class="absolute -right-20 -top-16 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
            <div class="relative">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 backdrop-blur">
                        <i data-lucide="receipt-text" class="h-5 w-5"></i>
                    </span>
                    <div>
                        <p class="text-[17px] font-extrabold tracking-tight">PatunganBeres</p>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/60">Bagi rata tanpa drama</p>
                    </div>
                </div>

                <h1 class="mt-14 text-[36px] font-extrabold leading-[1.12] tracking-tight">
                    Mulai patungan<br>yang <span class="text-[#bbf7d0]">beres.</span>
                </h1>
                <p class="mt-4 max-w-sm text-[13px] leading-relaxed text-white/75">
                    Satu akun untuk semua urusan patungan: split bill, iuran kas, sampai pengingat pelunasan.
                </p>
            </div>

            <div class="relative space-y-3">
                <div class="flex items-center gap-2.5 text-[12px] font-semibold text-white/85">
                    <i data-lucide="check-circle-2" class="h-4 w-4 text-[#bbf7d0]"></i> Scan struk otomatis dengan AI
                </div>
                <div class="flex items-center gap-2.5 text-[12px] font-semibold text-white/85">
                    <i data-lucide="check-circle-2" class="h-4 w-4 text-[#bbf7d0]"></i> Pembagian adil sampai rupiah terakhir
                </div>
                <div class="flex items-center gap-2.5 text-[12px] font-semibold text-white/85">
                    <i data-lucide="check-circle-2" class="h-4 w-4 text-[#bbf7d0]"></i> Link tagihan & verifikasi pembayaran
                </div>
            </div>
        </div>

        <div class="p-6 sm:p-10">
            <a href="{{ route('login') }}" class="mb-6 inline-flex items-center gap-1.5 text-[11.5px] font-bold text-muted hover:text-ink">
                <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i> Kembali ke halaman masuk
            </a>

            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-primary">Gratis, tanpa kartu kredit</p>
                <h2 class="mt-2 text-[28px] font-extrabold leading-tight tracking-tight text-ink">Buat akun baru</h2>
                <p class="mt-1.5 text-[12.5px] leading-relaxed text-muted">Isi data singkat, langsung bisa scan struk pertama.</p>
            </div>

            @if ($errors->any())
                <div class="mt-5 rounded-xl border border-danger/20 bg-danger/5 px-3.5 py-2.5 text-[11.5px] font-semibold text-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="mt-6 space-y-3.5">
                @csrf
                <div>
                    <label class="label" for="name">Nama Lengkap</label>
                    <input class="input" type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Contoh: Aiinin Ratna" required autofocus>
                </div>
                <div>
                    <label class="label" for="email">Email</label>
                    <input class="input" type="email" id="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com" required>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label" for="password">Kata Sandi</label>
                        <input class="input" type="password" id="password" name="password" placeholder="Min. 8 karakter" required>
                    </div>
                    <div>
                        <label class="label" for="password_confirmation">Ulangi Kata Sandi</label>
                        <input class="input" type="password" id="password_confirmation" name="password_confirmation" placeholder="Ulangi kata sandi" required>
                    </div>
                </div>
                <label class="flex items-start gap-2.5 text-[11.5px] font-medium leading-relaxed text-muted">
                    <input type="checkbox" name="terms" value="1" class="mt-0.5 rounded border-line text-primary focus:ring-primary" required>
                    Saya setuju dengan syarat & ketentuan serta kebijakan privasi PatunganBeres.
                </label>

                <button type="submit" class="btn btn-primary w-full justify-center py-3">
                    <i data-lucide="user-plus" class="h-4 w-4"></i>
                    Daftar Sekarang
                </button>
            </form>

            <p class="mt-5 text-center text-[12px] font-medium text-muted">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="font-bold text-primary hover:underline">Masuk di sini</a>
            </p>
        </div>
    </div>
</div>
@endsection
