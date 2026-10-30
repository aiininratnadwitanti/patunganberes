@php
    $statusMap = [
        'draft' => ['Draft', 'badge-neutral', 'bg-slate-400'],
        'waiting_confirmation' => ['Menunggu Konfirmasi', 'badge-warning', 'bg-warning'],
        'ready_to_share' => ['Siap Dibagikan', 'badge-info', 'bg-blue-500'],
        'pending_payment' => ['Menunggu Pembayaran', 'badge-warning', 'bg-warning'],
        'partially_paid' => ['Sebagian Lunas', 'badge-info', 'bg-blue-500'],
        'completed' => ['Lunas', 'badge-success', 'bg-primary'],
        'archived' => ['Diarsipkan', 'badge-neutral', 'bg-slate-400'],
    ];

    [$label, $class, $dot] = $statusMap[$bill->status] ?? [ucfirst($bill->status), 'badge-neutral', 'bg-slate-400'];
@endphp

<span class="badge {{ $class }}">
    <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>
    {{ $label }}
</span>
