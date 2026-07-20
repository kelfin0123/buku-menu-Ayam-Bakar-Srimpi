<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Ayam Bakar Srimpi - Digital Menu')</title>

    {{-- Cegah flash tema saat load: baca preferensi dark mode sebelum CSS dirender --}}
    <script>
        (function () {
            const theme = localStorage.getItem('theme');
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-app text-base font-sans antialiased">

    <header class="mobile-header">
        <button type="button" id="sidebarOpenBtn" class="mobile-menu-btn" aria-controls="siteSidebar" aria-expanded="false" aria-label="Buka menu navigasi">
            <span></span><span></span><span></span>
        </button>
        <a href="{{ route('home') }}" class="mobile-brand">
            <span aria-hidden="true">🔥</span>
            <span>Ayam Bakar <strong>Srimpi</strong></span>
        </a>
    </header>

    <button type="button" id="sidebarBackdrop" class="drawer-backdrop" aria-label="Tutup menu navigasi" tabindex="-1"></button>

    <div class="app-shell">
        {{-- Sidebar kiri --}}
        <x-sidebar />

        {{-- Konten utama --}}
        <main class="app-main">
            @yield('content')
        </main>

        {{-- Cart kanan (hanya tampil di halaman menu) --}}
        @hasSection('cart')
            @yield('cart')
        @endif
    </div>

</body>
</html>
