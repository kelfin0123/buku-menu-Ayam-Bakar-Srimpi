<article class="product-card" data-product-id="{{ $product['id'] }}">
    <div class="product-card-image-link">
        <img src="{{ $product['imageUrl'] ?: asset('images/no-image.png') }}"
             alt="{{ $product['name'] }}"
             class="product-card-image"
             loading="lazy"
             onerror="this.src='{{ asset('images/no-image.png') }}'">
    </div>

    <div class="product-card-body">
        <h3 class="product-card-title">{{ $product['name'] }}</h3>
        <p class="product-card-desc">{{ $product['description'] }}</p>
        <p class="product-card-desc">{{ $product['category'] }}</p>

        <div class="product-card-footer">
            <div>
            @if (!empty($product['badge']))
                <span class="product-promo-badge">{{ $product['badge'] }}</span>
            @endif
            <span class="product-card-price">
                @if (!empty($product['normalPrice']) && $product['normalPrice'] > $product['price'])
                    <del>Rp {{ number_format($product['normalPrice'], 0, ',', '.') }}</del>
                @endif
                Rp {{ number_format($product['price'], 0, ',', '.') }}
                </span>
                <div class="product-card-stock">Stok: {{ $product['stock'] }}</div>
            </div>

            <button type="button"
                    class="product-card-add-btn"
                    data-add-to-cart
                    data-id="{{ $product['id'] }}"
                    data-name="{{ $product['name'] }}"
                    data-price="{{ $product['price'] }}"
                    data-image="{{ $product['imageUrl'] ?: asset('images/no-image.png') }}"
                    aria-label="Tambah {{ $product['name'] }} ke keranjang">
                @include('components.icons.plus')
            </button>
        </div>
    </div>
</article>
