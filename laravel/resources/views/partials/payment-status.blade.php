@php
    $paymentMap = [
        'paid' => ['Lunas', 'badge-success', 'bg-primary'],
        'waiting_verification' => ['Menunggu Verifikasi', 'badge-warning', 'bg-warning'],
        'needs_fix' => ['Perlu Perbaikan', 'badge-danger', 'bg-danger'],
        'rejected' => ['Ditolak', 'badge-danger', 'bg-danger'],
        'unpaid' => ['Belum Bayar', 'badge-neutral', 'bg-slate-400'],
    ];

    [$label, $class, $dot] = $paymentMap[$status ?? 'unpaid'] ?? ['Belum Bayar', 'badge-neutral', 'bg-slate-400'];
@endphp

<span class="badge {{ $class }}">
    <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>
    {{ $label }}
</span>
