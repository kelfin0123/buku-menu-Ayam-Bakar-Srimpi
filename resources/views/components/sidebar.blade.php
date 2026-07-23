@php
    $menus = [
        ['label' => 'Beranda',      'icon' => 'home',    'href' => route('home'),      'active' => request()->routeIs('home')],
        ['label' => 'Makanan',      'icon' => 'drumstick','href' => route('menu.index', ['category' => 'makanan']),     'active' => request()->get('category') === 'makanan'],
        ['label' => 'Minuman',      'icon' => 'cup',     'href' => route('menu.index', ['category' => 'minuman']),     'active' => request()->get('category') === 'minuman'],
        ['label' => 'Paket Hemat',  'icon' => 'package', 'href' => route('menu.index', ['category' => 'paket-hemat']), 'active' => request()->get('category') === 'paket-hemat'],
        ['label' => 'Promo',        'icon' => 'fire',    'href' => route('menu.index', ['category' => 'promo']),       'active' => request()->get('category') === 'promo'],
        ['label' => 'Tentang Kami', 'icon' => 'info',    'href' => '#',                'active' => false],
    ];
@endphp

<aside class="sidebar" id="siteSidebar" aria-label="Navigasi utama">
    <div class="sidebar-top">
        <button type="button" id="sidebarCloseBtn" class="sidebar-close-btn" aria-label="Tutup menu navigasi">&times;</button>
        {{-- Logo --}}
        <a href="{{ route('home') }}" class="sidebar-logo">
            <span class="sidebar-logo-icon">🔥</span>
            <span class="sidebar-logo-text">
                Ayam Bakar
                <strong>Srimpi</strong>
            </span>
        </a>

        {{-- Navigasi --}}
        <nav class="sidebar-nav">
            @foreach ($menus as $menu)
                <a href="{{ $menu['href'] }}"
                   class="sidebar-link {{ $menu['active'] ? 'sidebar-link-active' : '' }}">
                    <span class="sidebar-link-icon">
                        @include('components.icons.' . $menu['icon'])
                    </span>
                    <span>{{ $menu['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    <div class="sidebar-bottom">
        {{-- Mode Gelap --}}
        <button type="button" id="darkModeToggle" class="sidebar-darkmode-btn">
            <span class="sidebar-link-icon" id="darkModeIcon">
                @include('components.icons.moon')
            </span>
            <span>Mode Gelap</span>
        </button>
    </div>
</aside>
