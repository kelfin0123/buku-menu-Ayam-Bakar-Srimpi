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

<aside class="sidebar">
    <div class="sidebar-top">
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
        {{-- Banner Gratis Ongkir --}}
        <div class="sidebar-promo-card">
            <p class="sidebar-promo-title">Gratis Ongkir</p>
            <p class="sidebar-promo-desc">Min. belanja<br><span>Rp 30.000</span></p>
            <img src="{{ asset('images/icons/delivery-scooter.png') }}" alt="" class="sidebar-promo-img" onerror="this.style.display='none'">
            <a href="{{ route('checkout.index') }}" class="sidebar-promo-btn">Pesan Sekarang</a>
        </div>

        {{-- Mode Gelap --}}
        <button type="button" id="darkModeToggle" class="sidebar-darkmode-btn">
            <span class="sidebar-link-icon" id="darkModeIcon">
                @include('components.icons.moon')
            </span>
            <span>Mode Gelap</span>
        </button>
    </div>
</aside>
