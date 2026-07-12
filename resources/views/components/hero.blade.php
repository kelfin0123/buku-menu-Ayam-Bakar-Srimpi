@php
    // Idealnya data ini berasal dari tabel `banners` via Controller.
    // Untuk saat ini disediakan default agar komponen tetap reusable.
    $slides = $slides ?? [
        [
            'title_line1' => 'Ayam Bakar',
            'title_line2' => 'Srimpi',
            'desc'  => 'Bakar dengan bumbu pilihan, cita rasa khas yang selalu dirindukan.',
            'image' => asset('images/banner/hero-1.jpg'),
            'cta'   => route('menu.index'),
        ],
        [
            'title_line1' => 'Paket Hemat',
            'title_line2' => 'Keluarga',
            'desc'  => 'Nikmati promo spesial untuk makan bersama keluarga tercinta.',
            'image' => asset('images/banner/hero-2.jpg'),
            'cta'   => route('menu.index', ['category' => 'paket-hemat']),
        ],
        [
            'title_line1' => 'Minuman Segar',
            'title_line2' => 'Setiap Hari',
            'desc'  => 'Pelepas dahaga dengan pilihan minuman favorit pelanggan.',
            'image' => asset('images/banner/hero-3.jpg'),
            'cta'   => route('menu.index', ['category' => 'minuman']),
        ],
    ];
@endphp

<section class="hero-slider" id="heroSlider" data-autoplay="5000">
    <div class="hero-track">
        @foreach ($slides as $i => $slide)
            <div class="hero-slide {{ $i === 0 ? 'is-active' : '' }}" data-index="{{ $i }}">
                <div class="hero-content">
                    <h1 class="hero-title">
                        {{ $slide['title_line1'] }}
                        <span>{{ $slide['title_line2'] }}</span>
                    </h1>
                    <p class="hero-desc">{{ $slide['desc'] }}</p>
                    <a href="{{ $slide['cta'] }}" class="hero-btn">
                        Lihat Menu
                        <span>@include('components.icons.chevron-right')</span>
                    </a>
                </div>
                <div class="hero-image-wrap">
                    <img src="{{ $slide['image'] }}" alt="{{ $slide['title_line1'] }}" class="hero-image" onerror="this.src='https://images.unsplash.com/photo-1598515213692-5f252f5c2b2f?w=900&q=80'">
                </div>
            </div>
        @endforeach
    </div>

    <button type="button" class="hero-nav hero-nav-prev" id="heroPrev" aria-label="Sebelumnya">
        @include('components.icons.chevron-left')
    </button>
    <button type="button" class="hero-nav hero-nav-next" id="heroNext" aria-label="Berikutnya">
        @include('components.icons.chevron-right')
    </button>

    <div class="hero-indicators" id="heroIndicators">
        @foreach ($slides as $i => $slide)
            <button type="button" class="hero-dot {{ $i === 0 ? 'is-active' : '' }}" data-index="{{ $i }}" aria-label="Slide {{ $i + 1 }}"></button>
        @endforeach
    </div>
</section>
