@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
<div class="mx-auto max-w-3xl space-y-3">
    <section class="card flex flex-col gap-2.5 p-3.5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-light text-primary-dark"><i data-lucide="bell" class="h-4 w-4"></i></span>
            <div>
                <p class="text-[12px] font-extrabold text-ink">Pusat Notifikasi</p>
                <p class="text-[10px] text-muted">{{ $unread }} belum dibaca · {{ $notifications->count() }} total</p>
            </div>
        </div>

        <div class="flex items-center gap-1.5">
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-ghost btn-sm" @disabled($unread === 0)>
                    <i data-lucide="check-check" class="h-3.5 w-3.5"></i> Tandai Semua
                </button>
            </form>
            <button type="button" class="btn btn-ghost btn-sm text-danger" @disabled($notifications->isEmpty())
                    data-confirm-delete
                    data-confirm-title="Hapus semua notifikasi?"
                    data-confirm-message="Seluruh riwayat notifikasi akan dihapus permanen."
                    data-confirm-url="{{ route('notifications.clear') }}"
                    data-confirm-method="DELETE"
                    data-confirm-label="Hapus Semua">
                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Bersihkan
            </button>
        </div>
    </section>

    @if ($notifications->isEmpty())
        <section class="card px-5 py-16 text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-light text-primary"><i data-lucide="bell-off" class="h-6 w-6"></i></span>
            <h3 class="mt-3 text-[15px] font-extrabold text-ink">Belum ada notifikasi</h3>
            <p class="mx-auto mt-1 max-w-xs text-[11px] leading-relaxed text-muted">Semua kabar tentang split bill dan iuran akan muncul di sini.</p>
        </section>
    @else
        <section class="card divide-y divide-line overflow-hidden">
            @foreach ($notifications as $notification)
                <div class="group flex items-start gap-3 p-3.5 transition {{ $notification->is_read ? '' : 'bg-light/25' }}">
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl
                        {{ $notification->tone === 'primary' ? 'bg-light text-primary-dark' : '' }}
                        {{ $notification->tone === 'warning' ? 'bg-warning/10 text-warning' : '' }}
                        {{ $notification->tone === 'danger' ? 'bg-danger/10 text-danger' : '' }}
                        {{ $notification->tone === 'neutral' ? 'bg-slate-100 text-slate-500' : '' }}">
                        <i data-lucide="{{ $notification->icon }}" class="h-4 w-4"></i>
                    </span>

                    <a href="{{ route('notifications.open', $notification) }}" class="min-w-0 flex-1">
                        <div class="flex items-start gap-2">
                            <p class="flex-1 text-[12px] font-bold leading-snug {{ $notification->is_read ? 'text-ink' : 'text-primary-dark' }}">{{ $notification->title }}</p>
                            @if (! $notification->is_read)
                                <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-primary"></span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-[11px] leading-relaxed text-muted">{{ $notification->message }}</p>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="text-[9.5px] font-medium text-muted">{{ $notification->created_at?->diffForHumans() }}</span>
                            @if ($notification->related_id)
                                <span class="badge badge-neutral text-[8.5px]">{{ $notification->related_id }}</span>
                            @endif
                        </div>
                    </a>

                    <div class="flex shrink-0 items-center gap-0.5 opacity-100 sm:opacity-0 sm:transition sm:group-hover:opacity-100">
                        @if (! $notification->is_read)
                            <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="icon-btn h-7 w-7" title="Tandai dibaca">
                                    <i data-lucide="check" class="h-3.5 w-3.5"></i>
                                </button>
                            </form>
                        @endif
                        <button type="button" class="icon-btn h-7 w-7 text-danger"
                                data-confirm-delete
                                data-confirm-title="Hapus notifikasi?"
                                data-confirm-message="{{ $notification->title }}"
                                data-confirm-url="{{ route('notifications.destroy', $notification) }}"
                                data-confirm-method="DELETE"
                                data-confirm-label="Hapus">
                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                        </button>
                    </div>
                </div>
            @endforeach
        </section>
    @endif
</div>
@endsection
