<div class="product-grid" id="productGrid">
    @if (!empty($firestoreError))
        <p class="product-grid-empty" role="alert">{{ $firestoreError }}</p>
    @elseif ($products->isEmpty())
        <p class="product-grid-empty">
            {{ request('category') === 'promo'
                ? 'Belum ada promo aktif saat ini.'
                : 'Belum ada produk aktif saat ini.' }}
        </p>
    @else
        @foreach ($products as $product)
            <x-product-card :product="$product" />
        @endforeach
    @endif
</div>
