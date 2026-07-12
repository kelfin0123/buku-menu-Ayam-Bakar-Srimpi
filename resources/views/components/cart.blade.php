<aside class="cart-panel" id="cartPanel">
    <div class="cart-card">
        <div class="cart-header">
            <h2>Pesanan Anda</h2>
            <button type="button" id="cartClearBtn" class="cart-clear-btn" aria-label="Hapus semua pesanan">
                @include('components.icons.trash')
            </button>
        </div>

        <div class="cart-items" id="cartItems">
            {{-- Item cart di-render oleh cart.js dari localStorage --}}
            <p class="cart-empty" id="cartEmptyState">Keranjang masih kosong.</p>
        </div>

        <div class="cart-summary">
            <div class="cart-summary-row">
                <span>Subtotal</span>
                <span id="cartSubtotal">Rp 0</span>
            </div>
            <div class="cart-summary-row">
                <span>Ongkir</span>
                <span id="cartShipping">Rp 0</span>
            </div>
            <div class="cart-summary-row cart-summary-total">
                <span>Total</span>
                <span id="cartTotal">Rp 0</span>
            </div>
        </div>

        <button type="button" id="cartCheckoutBtn" class="cart-checkout-btn">
            Pesan Sekarang
            <span>@include('components.icons.send')</span>
        </button>
    </div>

    <div class="cart-trust-card">
        <div class="cart-trust-item">
            <span class="cart-trust-icon cart-trust-icon-green">@include('components.icons.shield')</span>
            <div>
                <p class="cart-trust-title">100% Aman</p>
                <p class="cart-trust-desc">Transaksi aman dan terenkripsi</p>
            </div>
        </div>
        <div class="cart-trust-item">
            <span class="cart-trust-icon cart-trust-icon-orange">@include('components.icons.bolt')</span>
            <div>
                <p class="cart-trust-title">Cepat</p>
                <p class="cart-trust-desc">Pesanan diproses dengan cepat</p>
            </div>
        </div>
        <div class="cart-trust-item">
            <span class="cart-trust-icon cart-trust-icon-pink">@include('components.icons.tag')</span>
            <div>
                <p class="cart-trust-title">Harga Terbaik</p>
                <p class="cart-trust-desc">Kualitas terbaik harga bersahabat</p>
            </div>
        </div>
    </div>
</aside>

{{-- Template satu baris item cart, di-clone oleh cart.js --}}
<template id="cartItemTemplate">
    <div class="cart-item" data-cart-item>
        <img class="cart-item-img" data-role="image" src="" alt="">
        <div class="cart-item-info">
            <div class="cart-item-top">
                <p class="cart-item-name" data-role="name"></p>
                <button type="button" class="cart-item-remove" data-role="remove" aria-label="Hapus item">
                    @include('components.icons.close')
                </button>
            </div>
            <div class="cart-item-bottom">
                <div class="cart-item-qty">
                    <button type="button" data-role="decrease" aria-label="Kurangi">@include('components.icons.minus')</button>
                    <span data-role="qty">1</span>
                    <button type="button" data-role="increase" aria-label="Tambah">@include('components.icons.plus')</button>
                </div>
                <span class="cart-item-price" data-role="price"></span>
            </div>
        </div>
    </div>
</template>
