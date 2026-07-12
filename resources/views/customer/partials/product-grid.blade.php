<div class="product-grid" id="productGrid">
    @forelse ($products as $product)
        <x-product-card :product="$product" />
    @empty
        <p class="product-grid-empty">Menu tidak ditemukan.</p>
    @endforelse
</div>
