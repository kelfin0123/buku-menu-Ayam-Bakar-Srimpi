@extends('layouts.app')

@section('title', $product->name . ' - Ayam Bakar Srimpi')

@section('content')
    <header class="topbar">
        <h1 class="checkout-heading">{{ $product->name }}</h1>
    </header>

    <section class="product-detail-wrap">
        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="product-detail-image">
        <div class="product-detail-info">
            <h2>{{ $product->name }}</h2>
            <p>{{ $product->description }}</p>
            <p class="product-detail-price">Rp {{ number_format($product->final_price, 0, ',', '.') }}</p>
            <button type="button"
                    class="product-card-add-btn product-detail-add-btn"
                    data-add-to-cart
                    data-id="{{ $product->id }}"
                    data-name="{{ $product->name }}"
                    data-price="{{ $product->final_price }}"
                    data-image="{{ $product->image_url }}">
                Tambah ke Keranjang
            </button>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <h3 class="related-heading">Menu Lainnya</h3>
        <div class="product-grid">
            @foreach ($related as $item)
                <x-product-card :product="$item" />
            @endforeach
        </div>
    @endif
@endsection
