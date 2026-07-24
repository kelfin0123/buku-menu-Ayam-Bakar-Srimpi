@props(['slides' => collect()])

@php
    $fallback = collect([[
        'title' => 'Selamat Datang',
        'highlight_text' => '',
        'subtitle' => 'Temukan menu favorit Anda.',
        'description' => null,
        'button_text' => 'Lihat Menu',
        'button_url' => '#menu',
        'resolved_image_url' => null,
    ]]);
    $items = $slides->isNotEmpty() ? $slides : $fallback;
@endphp

<section class="hero-slider" id="heroSlider" data-autoplay="5000">
    <div class="hero-track">
        @foreach ($items as $i => $slide)
            @php
                $value = is_array($slide) ? $slide : $slide->toArray();
                $image = is_array($slide)
                    ? ($value['resolved_image_url'] ?? null)
                    : $slide->resolved_image_url;
            @endphp
            <div class="hero-slide {{ $i === 0 ? 'is-active' : '' }}" data-index="{{ $i }}">
                <div class="hero-content">
                    <h1 class="hero-title">
                        {{ $value['title'] }}
                        @if (!empty($value['highlight_text']))
                            <span>{{ $value['highlight_text'] }}</span>
                        @endif
                    </h1>
                    @if (!empty($value['subtitle']))
                        <p class="hero-subtitle">{{ $value['subtitle'] }}</p>
                    @endif
                    @if (!empty($value['description']))
                        <p class="hero-desc">{{ $value['description'] }}</p>
                    @endif
                    @if (!empty($value['button_text']))
                        <a href="{{ $value['button_url'] ?: '#menu' }}" class="hero-btn">
                            {{ $value['button_text'] }}
                            <span>@include('components.icons.chevron-right')</span>
                        </a>
                    @endif
                </div>
                @if ($image)
                    <div class="hero-image-wrap">
                        <img src="{{ $image }}" alt="{{ $value['title'] }}"
                             class="hero-image" loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                             onerror="this.closest('.hero-image-wrap').remove()">
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($items->count() > 1)
        <button type="button" class="hero-nav hero-nav-prev" id="heroPrev" aria-label="Sebelumnya">
            @include('components.icons.chevron-left')
        </button>
        <button type="button" class="hero-nav hero-nav-next" id="heroNext" aria-label="Berikutnya">
            @include('components.icons.chevron-right')
        </button>
        <div class="hero-indicators" id="heroIndicators">
            @foreach ($items as $i => $slide)
                <button type="button" class="hero-dot {{ $i === 0 ? 'is-active' : '' }}"
                        data-index="{{ $i }}" aria-label="Slide {{ $i + 1 }}"></button>
            @endforeach
        </div>
    @endif
</section>
