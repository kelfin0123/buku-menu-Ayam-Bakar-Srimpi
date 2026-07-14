@extends('layouts.app')

@section('title', 'Checkout - Ayam Bakar Srimpi')

@section('content')
    <header class="topbar">
        <h1 class="checkout-heading">Checkout</h1>
    </header>

    <section class="checkout-wrap">
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="checkoutForm" class="checkout-form" method="POST" action="{{ route('checkout.store') }}">
            @csrf
            <div class="checkout-field">
                <label for="customer_name">Nama Customer</label>
                <input type="text" name="customer_name" id="customer_name" required placeholder="Masukkan nama Anda" value="{{ old('customer_name') }}">
            </div>
            <div class="checkout-field">
                <label for="table_number">Nomor Meja</label>
                <input type="text" name="table_number" id="table_number" required placeholder="Contoh: A1, B2" value="{{ old('table_number') }}">
            </div>

            {{-- Payment Method Selection --}}
            <div class="checkout-field">
                <label>Metode Pembayaran</label>
                <div class="payment-methods">
                    <label class="payment-method-option">
                        <input type="radio" name="payment_method" value="cash" required {{ old('payment_method') == 'cash' ? 'checked' : '' }}>
                        <span class="payment-method-icon">💵</span>
                        <span class="payment-method-label">Tunai</span>
                    </label>
                    <label class="payment-method-option">
                        <input type="radio" name="payment_method" value="qris" required {{ old('payment_method') == 'qris' ? 'checked' : '' }}>
                        <span class="payment-method-icon">📱</span>
                        <span class="payment-method-label">QRIS</span>
                    </label>
                </div>
            </div>

            {{-- Ringkasan diambil dari localStorage lewat cart.js lalu disuntikkan sebagai hidden input --}}
            <input type="hidden" name="items" id="checkoutItemsInput">

            <div id="checkoutSummary" class="checkout-summary"></div>

            <button type="submit" class="cart-checkout-btn">
                Bayar Sekarang
                <span>@include('components.icons.send')</span>
            </button>
        </form>
    </section>

    <script>
        // Load cart data and populate hidden input
        document.addEventListener('DOMContentLoaded', function() {
            const cart = JSON.parse(localStorage.getItem('ayam_bakar_srimpi_cart')) || [];
            const itemsInput = document.getElementById('checkoutItemsInput');
            
            if (cart.length === 0) {
                document.getElementById('checkoutSummary').innerHTML = '<p class="empty-cart">Keranjang kosong</p>';
                document.querySelector('.cart-checkout-btn').disabled = true;
                return;
            }
            
            // Convert cart to items array format
            const items = cart.map(item => ({
                product_id: item.id,
                qty: item.qty
            }));
            
            itemsInput.value = JSON.stringify(items);
            
            // Render summary
            renderCheckoutSummary(cart);
        });

        function renderCheckoutSummary(cart) {
            const summaryContainer = document.getElementById('checkoutSummary');
            const subtotal = cart.reduce((sum, item) => sum + item.price * item.qty, 0);
            
            let html = '<div class="checkout-items">';
            
            cart.forEach(item => {
                html += `
                    <div class="checkout-item">
                        <div class="checkout-item-info">
                            <span class="checkout-item-name">${item.name}</span>
                            <span class="checkout-item-qty">x${item.qty}</span>
                        </div>
                        <span class="checkout-item-price">${formatRupiah(item.price * item.qty)}</span>
                    </div>
                `;
            });
            
            html += '</div>';
            
            html += `
                <div class="checkout-total">
                    <div class="checkout-total-row">
                        <span>Subtotal</span>
                        <span>${formatRupiah(subtotal)}</span>
                    </div>
                    <div class="checkout-total-row checkout-total-final">
                        <span>Total</span>
                        <span>${formatRupiah(subtotal)}</span>
                    </div>
                </div>
            `;
            
            summaryContainer.innerHTML = html;
        }

        function formatRupiah(number) {
            return 'Rp ' + Number(number).toLocaleString('id-ID');
        }
    </script>

    <style>
        .payment-methods {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .payment-method-option {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .payment-method-option:hover {
            border-color: #f59e0b;
        }
        
        .payment-method-option input[type="radio"]:checked + span + span {
            color: #f59e0b;
            font-weight: 600;
        }
        
        .payment-method-option input[type="radio"]:checked {
            accent-color: #f59e0b;
        }
        
        .payment-method-icon {
            font-size: 1.5rem;
        }
        
        .payment-method-label {
            font-size: 0.875rem;
        }
        
        .checkout-items {
            margin-bottom: 1.5rem;
        }
        
        .checkout-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .checkout-item:last-child {
            border-bottom: none;
        }
        
        .checkout-item-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .checkout-item-name {
            font-weight: 500;
            color: #1f2937;
        }
        
        .checkout-item-qty {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .checkout-item-price {
            font-weight: 600;
            color: #f59e0b;
        }
        
        .checkout-total {
            background: #fef3c7;
            padding: 1rem;
            border-radius: 0.5rem;
        }
        
        .checkout-total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
        }
        
        .checkout-total-final {
            font-weight: 700;
            font-size: 1.125rem;
            color: #f59e0b;
            border-top: 1px solid #fcd34d;
            margin-top: 0.5rem;
            padding-top: 0.75rem;
        }
        
        .empty-cart {
            text-align: center;
            color: #6b7280;
            padding: 2rem;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-danger ul {
            margin: 0;
            padding-left: 1.5rem;
        }
    </style>
@endsection
