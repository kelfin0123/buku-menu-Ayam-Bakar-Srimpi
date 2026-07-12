@php
    $active = $active ?? 'semua';
@endphp

<div class="category-bar" id="categoryBar">
    <button type="button"
            class="category-pill {{ $active === 'semua' ? 'is-active' : '' }}"
            data-category="semua">
        <span class="category-pill-icon">@include('components.icons.grid')</span>
        Semua
    </button>

    @foreach ($categories as $category)
        <button type="button"
                class="category-pill {{ $active === $category->slug ? 'is-active' : '' }}"
                data-category="{{ $category->slug }}">
            <span class="category-pill-icon">
                @include('components.icons.' . ($category->icon ?? 'tag'))
            </span>
            {{ $category->name }}
        </button>
    @endforeach
</div>
