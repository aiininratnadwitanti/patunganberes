@extends('layouts.guest')

@section('title', 'Masuk')

@section('content')
<div class="mx-auto flex min-h-screen w-full max-w-6xl items-center justify-center px-4 py-8 sm:px-6">
    <div class="grid w-full overflow-hidden rounded-[28px] border border-line bg-white shadow-[0_30px_80px_-50px_rgba(23,35,28,0.45)] lg:grid-cols-[1.05fr_1fr]">
        <div class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-[#14532d] via-[#166534] to-[#16a34a] p-10 text-white lg:flex">
            <div class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -bottom-24 -left-10 h-64 w-64 rounded-full bg-[#10b981]/30 blur-3xl"></div>

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

                <h1 class="mt-14 max-w-md text-[38px] font-extrabold leading-[1.12] tracking-tight">
                    Foto struknya.<br>Biarkan AI yang<br><span class="text-[#bbf7d0]">bagi rata.</span>
                </h1>
                <p class="mt-4 max-w-sm text-[13px] leading-relaxed text-white/75">
                    Dari struk resto sampai iuran kas kelas. Hitung porsi, pajak, servis, dan diskon otomatis sampai rupiah terakhir.
                </p>
            </div>

            <div class="relative mt-10 grid gap-3">
                <div class="flex items-center gap-3 rounded-2xl border border-white/15 bg-white/10 p-3 backdrop-blur">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15"><i data-lucide="scan-line" class="h-4 w-4"></i></span>
                    <div>
                        <p class="text-[12px] font-bold">Scan struk dengan Gemini AI</p>
                        <p class="text-[10.5px] text-white/60">Item, harga, pajak terbaca otomatis.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-2xl border border-white/15 bg-white/10 p-3 backdrop-blur">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/15"><i data-lucide="users" class="h-4 w-4"></i></span>
                    <div>
                        <p class="text-[12px] font-bold">Link tagihan untuk tiap teman</p>
                        <p class="text-[10.5px] text-white/60">Bagikan lewat WhatsApp, pantau pelunasan.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6 sm:p-10">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <span class="brand-mark"><i data-lucide="receipt-text" class="h-5 w-5"></i></span>
                <div>
                    <p class="text-[16px] font-extrabold tracking-tight text-ink">PatunganBeres</p>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-muted">Bagi rata tanpa drama</p>
                </div>
            </div>

            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-primary">Selamat datang kembali</p>
                <h2 class="mt-2 text-[28px] font-extrabold leading-tight tracking-tight text-ink">Masuk ke akunmu</h2>
                <p class="mt-1.5 text-[12.5px] leading-relaxed text-muted">Lanjutkan urusan patungan yang belum beres.</p>
            </div>

            @if ($errors->any())
                <div class="mt-5 rounded-xl border border-danger/20 bg-danger/5 px-3.5 py-2.5 text-[11.5px] font-semibold text-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label class="label" for="email">Email</label>
                    <input class="input" type="email" id="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com" required autofocus>
                </div>
                <div>
                    <label class="label" for="password">Kata Sandi</label>
                    <input class="input" type="password" id="password" name="password" placeholder="Minimal 8 karakter" required>
                </div>
                <label class="flex items-center gap-2 text-[11.5px] font-semibold text-muted">
                    <input type="checkbox" name="remember" value="1" class="rounded border-line text-primary focus:ring-primary">
                    Ingat saya di perangkat ini
                </label>

                <button type="submit" class="btn btn-primary w-full justify-center py-3">
                    <i data-lucide="log-in" class="h-4 w-4"></i>
                    Masuk
                </button>
            </form>

            <p class="mt-6 text-center text-[12px] font-medium text-muted">
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-bold text-primary hover:underline">Daftar gratis</a>
            </p>
        </div>
    </div>
</div>
@endsection
