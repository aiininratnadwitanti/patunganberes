<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PatunganBeres')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style type="text/tailwindcss">
        @theme {
            --color-primary: #16a34a;
            --color-primary-dark: #166534;
            --color-emerald: #10b981;
            --color-light: #dcfce7;
            --color-bg: #f7faf7;
            --color-ink: #17231c;
            --color-muted: #6b7a70;
            --color-line: #e5e7eb;
            --color-warning: #f59e0b;
            --color-danger: #ef4444;
            --font-sans: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    {{-- app.js harus dieksekusi SEBELUM Alpine start, agar Alpine.data/global
         component dan Alpine.store sudah terdaftar saat Alpine menginisialisasi halaman. --}}
    <script defer src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="guest-body">
    @yield('content')

    @include('partials.toasts')
    @include('partials.confirm-modal')

    @stack('scripts')
</body>
</html>
