<article class="product-card" data-product-id="{{ $product->id }}">
    <a href="{{ route('product.show', $product->slug) }}" class="product-card-image-link">
        <img src="{{ $product->image_url }}"
             alt="{{ $product->name }}"
             class="product-card-image"
             loading="lazy"
             onerror="this.src='https://images.unsplash.com/photo-1598103442097-8b74394b95c6?w=500&q=80'">
        @if ($product->is_promo)
            <span class="product-card-badge">Promo</span>
        @endif
    </a>

    <div class="product-card-body">
        <h3 class="product-card-title">{{ $product->name }}</h3>
        <p class="product-card-desc">{{ $product->description }}</p>

        <div class="product-card-footer">
            <span class="product-card-price">
                Rp {{ number_format($product->final_price, 0, ',', '.') }}
            </span>

            <button type="button"
                    class="product-card-add-btn"
                    data-add-to-cart
                    data-id="{{ $product->firestore_id }}"
                    data-name="{{ $product->name }}"
                    data-price="{{ $product->final_price }}"
                    data-image="{{ $product->image_url }}"
                    data-debug-id="{{ $product->id }}"
                    data-debug-firestore="{{ $product->firestore_id }}"
                    aria-label="Tambah {{ $product->name }} ke keranjang">
                @include('components.icons.plus')
            </button>
        </div>
    </div>
</article>
