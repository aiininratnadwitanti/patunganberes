<header class="sticky top-0 z-30 border-b border-line/80 bg-bg/85 backdrop-blur-xl">
    <div class="mx-auto flex w-full max-w-[1440px] items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
        <div class="ml-auto flex shrink-0 items-center gap-2">
            <span class="hidden items-center gap-1.5 rounded-full border border-line bg-white px-3 py-1.5 text-[11px] font-semibold text-muted sm:flex">
                <i data-lucide="calendar-days" class="h-3.5 w-3.5 text-primary"></i>
                {{ now()->locale('id')->translatedFormat('d M Y') }}
            </span>

            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" class="icon-btn relative h-10 w-10 border border-line bg-white" @click="open = !open" aria-label="Notifikasi">
                    <i data-lucide="bell" class="h-[18px] w-[18px]"></i>
                    @if (($unreadCount ?? 0) > 0)
                        <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[9px] font-bold text-white">{{ $unreadCount }}</span>
                    @endif
                </button>

                <div x-show="open" x-transition.origin.top.right x-cloak class="absolute right-0 mt-2 w-[320px] max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-line bg-white shadow-xl">
                    <div class="flex items-center justify-between border-b border-line px-4 py-3">
                        <p class="text-[13px] font-bold text-ink">Notifikasi</p>
                        <a href="{{ route('notifications.index') }}" class="text-[11px] font-semibold text-primary hover:underline">Lihat semua</a>
                    </div>
                    <div class="max-h-80 divide-y divide-line overflow-y-auto">
                        @forelse (($navNotifications ?? collect()) as $notification)
                            <div class="flex items-start gap-1 px-4 py-3 transition hover:bg-light/50">
                                <a href="{{ route('notifications.open', $notification) }}" class="flex min-w-0 flex-1 gap-3">
                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl {{ $notification->is_read ? 'bg-slate-100 text-slate-500' : 'bg-light text-primary' }}">
                                        <i data-lucide="{{ $notification->icon }}" class="h-4 w-4"></i>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="flex items-center gap-1.5">
                                            <span class="truncate text-[12px] font-bold text-ink">{{ $notification->title }}</span>
                                            @if (! $notification->is_read)
                                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary"></span>
                                            @endif
                                        </span>
                                        <span class="mt-0.5 line-clamp-2 block text-[11px] leading-relaxed text-muted">{{ $notification->message }}</span>
                                        <span class="mt-1 block text-[10px] font-medium text-muted">{{ $notification->created_at?->diffForHumans() }}</span>
                                    </span>
                                </a>
                                <button type="button" class="icon-btn h-7 w-7 shrink-0 text-danger" title="Hapus notifikasi"
                                        data-confirm-delete
                                        data-confirm-title="Hapus notifikasi?"
                                        data-confirm-message="{{ $notification->title }}"
                                        data-confirm-url="{{ route('notifications.destroy', $notification) }}"
                                        data-confirm-method="DELETE"
                                        data-confirm-label="Hapus">
                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                </button>
                            </div>
                        @empty
                            <p class="px-4 py-8 text-center text-[12px] text-muted">Belum ada notifikasi.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <a href="{{ route('profile.edit') }}" class="hidden items-center gap-2 rounded-full border border-line bg-white py-1 pl-1 pr-3 sm:flex">
                <img src="{{ $navUser->avatar }}" alt="{{ $navUser->name ?? '' }}" class="h-8 w-8 rounded-full object-cover">
                <span class="text-[12px] font-bold text-ink">{{ $navUser->first_name ?? 'Profil' }}</span>
            </a>
        </div>
    </div>

</header>
