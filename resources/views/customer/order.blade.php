@extends('layouts.app')

@section('title', 'Pesanan ' . $order->order_code)

@section('content')
    <header class="topbar">
        <h1 class="checkout-heading">Detail Pesanan</h1>
    </header>

    <section class="order-wrap">
        <div class="order-card">
            <p class="order-code">{{ $order->order_code }}</p>
            <p id="orderStatus" class="order-status order-status-{{ $order->status }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</p>

            <div class="order-items">
                @foreach ($order->items as $item)
                    <div class="order-item-row">
                        <span>{{ $item->product_name }} x{{ $item->qty }}</span>
                        <span>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>

            <div class="cart-summary">
                <div class="cart-summary-row">
                    <span>Subtotal</span>
                    <span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
                </div>
                <div class="cart-summary-row cart-summary-total">
                    <span>Total</span>
                    <span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
                </div>
            </div>

            @if ($whatsappUrl)
                <a
                    class="cart-checkout-btn"
                    href="{{ $whatsappUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Kirim Nota ke WhatsApp
                </a>
            @else
                <button class="cart-checkout-btn" type="button" disabled>
                    Nomor WhatsApp pelanggan belum tersedia
                </button>
            @endif

            @if (!$order->payment_method && $order->status === \App\Models\Order::STATUS_WAITING_PAYMENT)
                <div class="payment-method-section">
                    <h2>Pilih Metode Pembayaran</h2>
                    <form method="POST" action="{{ route('checkout.payment.select', $order->order_code) }}">
                        @csrf
                        <div class="payment-methods">
                            <label class="payment-method-option">
                                <input type="radio" name="payment_method" value="cash" required>
                                <strong>Bayar Tunai</strong>
                                <span>Bayar langsung kepada kasir.</span>
                            </label>
                            <label class="payment-method-option">
                                <input type="radio" name="payment_method" value="qris" required>
                                <strong>Bayar QRIS</strong>
                                <span>Bayar melalui Midtrans QRIS.</span>
                            </label>
                        </div>
                        @error('payment_method')<p class="product-grid-empty">{{ $message }}</p>@enderror
                        <button type="submit" class="cart-checkout-btn">Lanjutkan Pembayaran</button>
                    </form>
                </div>
            @elseif ($order->payment_method)
                <a class="cart-checkout-btn" href="{{ route('checkout.payment', $order->order_code) }}">
                    Lihat Pembayaran {{ strtoupper($order->payment_method) }}
                </a>
            @endif
        </div>
    </section>
@endsection

@if (!in_array($order->status, [\App\Models\Order::STATUS_COMPLETED, \App\Models\Order::STATUS_CANCELLED, \App\Models\Order::STATUS_EXPIRED], true))
    <script>
        setInterval(async () => {
            try {
                const response = await fetch(@json(route('checkout.status', $order->order_code)), {
                    headers: {'Accept': 'application/json'}
                });
                if (!response.ok) return;
                const payload = await response.json();
                const status = payload.data?.status;
                if (status) document.getElementById('orderStatus').textContent = status.replaceAll('_', ' ');
            } catch (_) {}
        }, 3000);
    </script>
@endif
