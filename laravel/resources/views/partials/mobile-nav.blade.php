<nav class="mobile-nav">
    <div class="mobile-nav-inner">
        <a href="{{ route('dashboard') }}" class="mobile-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
            <i data-lucide="home" class="h-5 w-5"></i>
            <span>Beranda</span>
        </a>
        <a href="{{ route('history.index') }}" class="mobile-link {{ request()->routeIs('history.*', 'split-bills.*') ? 'is-active' : '' }}">
            <i data-lucide="history" class="h-5 w-5"></i>
            <span>Riwayat</span>
        </a>

        <a href="{{ route('split-bills.create') }}" class="mobile-scan" aria-label="Scan struk">
            <i data-lucide="scan-line" class="h-6 w-6"></i>
        </a>

        <a href="{{ route('iuran.index') }}" class="mobile-link {{ request()->routeIs('iuran.*') ? 'is-active' : '' }}">
            <i data-lucide="piggy-bank" class="h-5 w-5"></i>
            <span>Iuran</span>
        </a>
        <a href="{{ route('profile.edit') }}" class="mobile-link {{ request()->routeIs('profile.*') ? 'is-active' : '' }}">
            <i data-lucide="user-round" class="h-5 w-5"></i>
            <span>Profil</span>
        </a>
    </div>
</nav>
