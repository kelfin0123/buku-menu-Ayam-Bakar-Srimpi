<div class="product-grid" id="productGrid">
    @if (!empty($firestoreError))
        <p class="product-grid-empty" role="alert">{{ $firestoreError }}</p>
    @elseif ($products->isEmpty())
        <p class="product-grid-empty">Belum ada produk yang berhasil dibaca dari Firebase.</p>
    @else
        @foreach ($products as $product)
            <x-product-card :product="$product" />
        @endforeach
    @endif
</div>
