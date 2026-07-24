@extends('layouts.app')

@section('title', 'Beranda - Ayam Bakar Srimpi')

@section('content')

    {{-- Header: search bar + info pesanan --}}
    <header class="topbar">
        <div class="search-bar">
            <span class="search-bar-icon">@include('components.icons.search')</span>
            <input type="text"
                   id="searchInput"
                   class="search-bar-input"
                   placeholder="Cari menu favoritmu..."
                   value="{{ $searchTerm }}"
                   autocomplete="off">
            <button type="button" class="search-bar-btn" id="searchSubmitBtn" aria-label="Cari">
                @include('components.icons.search')
            </button>
        </div>

        <button type="button" class="topbar-orders-btn" id="viewOrdersBtn">
            <span>@include('components.icons.book')</span>
            Lihat Pesanan
            <span class="topbar-orders-count" id="topbarCartCount">0</span>
        </button>
    </header>

    {{-- Hero Slider --}}
    <x-hero :slides="$heroBanners" />

    {{-- Kategori --}}
    <x-category :categories="$categories" :active="$activeCategory" />

    {{-- Grid Produk --}}
    @include('customer.partials.product-grid', [
        'products' => $products,
        'firestoreError' => $firestoreError,
    ])

@endsection

@section('cart')
    <x-cart />
@endsection
