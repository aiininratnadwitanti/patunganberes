@extends('layouts.guest')

@section('title', 'Setor Iuran · '.$pool->title)

@section('content')
@php
    // Saran nominal dibulatkan ke ribuan terdekat agar angka bawaan selalu bulat.
    $rawSuggested = $pool->fee_per_person > 0
        ? $pool->fee_per_person
        : max(0, $pool->target_amount - $pool->current_collected);
    $suggested = max(1000, (int) (ceil($rawSuggested / 1000) * 1000));
@endphp

<div class="mx-auto w-full max-w-xl px-4 py-8">
    <div class="card overflow-hidden">
        <div class="border-b border-line bg-gradient-to-br from-light/70 to-white p-5">
            <span class="badge badge-neutral">{{ ucfirst(str_replace('_', ' ', $pool->category)) }}</span>
            <h1 class="mt-2 text-[21px] font-extrabold leading-tight tracking-tight text-ink">{{ $pool->title }}</h1>
            <p class="mt-1 text-[11.5px] leading-relaxed text-muted">{{ $pool->description ?: 'Iuran bersama' }}</p>
        </div>

        <div class="p-5">
            <div class="rounded-xl bg-[#f8faf9] p-3">
                <p class="text-[9px] font-bold uppercase tracking-[0.1em] text-muted">Rekening Iuran</p>
                <p class="mt-1 text-[12px] font-extrabold text-ink">{{ $pool->bank_name }}</p>
                <p class="text-[12px] font-bold text-primary-dark">{{ $pool->account_number }}</p>
                <p class="text-[10px] text-muted">a.n. {{ $pool->account_holder }}</p>
                <p class="mt-2 text-[10px] leading-relaxed text-muted">Transfer sesuai nominal setoranmu, lalu isi form di bawah beserta buktinya.</p>
            </div>

            @if ($errors->any())
                <p class="mt-4 rounded-xl border border-danger/20 bg-danger/5 px-3 py-2 text-[11.5px] font-semibold text-danger">{{ $errors->first() }}</p>
            @endif

            <form method="POST" action="{{ route('guest.iuran.report', [$pool->code, $pool->public_token]) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label class="label" for="name">Namamu</label>
                    <input class="input" type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Nama yang kamu pakai di grup" required>
                </div>

                <div>
                    <label class="label" for="amount">Jumlah yang kamu setor (Rp)</label>
                    <input class="input" type="number" min="1000" step="1000" inputmode="numeric" id="amount" name="amount"
                           value="{{ old('amount', $suggested) }}" required>
                    <p class="mt-1 text-[9.5px] text-muted">Isi angka bulat, mis. 10000, 25000, 50000.</p>
                </div>

                <div>
                    <label class="label" for="proof">Bukti pembayaran (foto/struk transfer)</label>
                    <input class="input" type="file" id="proof" name="proof" accept="image/*" capture="environment">
                    <p class="mt-1 text-[9.5px] text-muted">JPG/PNG, maksimal 5MB. Membantu pembuat iuran memverifikasi.</p>
                </div>

                <div>
                    <label class="label" for="notes">Catatan (opsional)</label>
                    <textarea class="textarea" id="notes" name="notes" rows="2" placeholder="Contoh: transfer dari rekening BCA a.n. ...">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary w-full justify-center py-3">
                    <i data-lucide="check" class="h-4 w-4"></i> Kirim Setoran
                </button>
                <p class="text-center text-[9.5px] leading-relaxed text-muted">Setoran langsung menambah total terkumpul dan berstatus <span class="font-bold text-ink">Menunggu Verifikasi</span> sampai pembuat iuran mengecek buktinya.</p>
            </form>
        </div>
    </div>
</div>
@endsection
