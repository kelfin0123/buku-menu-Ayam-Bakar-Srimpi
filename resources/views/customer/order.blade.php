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
            @if ($order->is_delivery)
                <div class="alert alert-danger">
                    <strong>Pesan Antar ke Rumah</strong><br>
                    Penerima: {{ $order->customer_name }}<br>
                    WhatsApp: {{ $order->customer_phone }}<br>
                    Alamat: {{ $order->delivery_address }}<br>
                    @if ($order->delivery_address_detail)
                        Patokan: {{ $order->delivery_address_detail }}<br>
                    @endif
                    @if ($order->delivery_note)
                        Catatan: {{ $order->delivery_note }}<br>
                    @endif
                    Ongkos kirim akan dikonfirmasi oleh kasir melalui WhatsApp.
                </div>
            @endif

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
                    <span>{{ $order->is_delivery ? 'Total Sementara' : 'Total' }}</span>
                    <span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
                </div>
                @if ($order->is_delivery)
                    <div class="cart-summary-row">
                        <span>Ongkos Kirim</span>
                        <span>{{ $order->delivery_fee_status === 'confirmed' ? 'Rp '.number_format($order->delivery_fee, 0, ',', '.') : 'Belum ditentukan' }}</span>
                    </div>
                @endif
            </div>

            @if (!$order->payment_method && $order->status === \App\Models\Order::STATUS_WAITING_PAYMENT)
                <div class="payment-method-section">
                    <h2>Pilih Metode Pembayaran</h2>
                    <form method="POST" action="{{ route('checkout.payment.select', $order->order_code) }}">
                        @csrf
                        <div class="payment-methods">
                            @unless ($order->is_delivery)
                                <label class="payment-method-option">
                                    <input type="radio" name="payment_method" value="cash" required>
                                    <strong>Bayar Tunai</strong>
                                    <span>Bayar langsung kepada kasir.</span>
                                </label>
                            @endunless
                            <label class="payment-method-option">
                                <input type="radio" name="payment_method" value="qris" required>
                                <strong>Bayar QRIS</strong>
                                <span>Bayar melalui Midtrans QRIS.</span>
                            </label>
                            @if ($order->is_delivery)
                                <label class="payment-method-option">
                                    <input type="radio" name="payment_method" value="bank_transfer" required>
                                    <strong>Transfer Bank</strong>
                                    <span>
                                        {{ config('services.bank_transfer.name') ?: 'Bank belum dikonfigurasi' }} —
                                        {{ config('services.bank_transfer.account_number') ?: '-' }} —
                                        {{ config('services.bank_transfer.account_holder') ?: '-' }}
                                    </span>
                                </label>
                            @endif
                        </div>
                        @error('payment_method')<p class="product-grid-empty">{{ $message }}</p>@enderror
                        <button type="submit" class="cart-checkout-btn">Lanjutkan Pembayaran</button>
                    </form>
                </div>
            @elseif ($order->payment_method === \App\Models\Order::PAYMENT_METHOD_BANK_TRANSFER)
                <div class="payment-method-section">
                    <h2>Transfer Bank</h2>
                    <p>Bank: {{ config('services.bank_transfer.name') ?: '-' }}</p>
                    <p>Nomor Rekening: <span id="bankAccountNumber">{{ config('services.bank_transfer.account_number') ?: '-' }}</span></p>
                    <p>Atas Nama: {{ config('services.bank_transfer.account_holder') ?: '-' }}</p>
                    <button class="cart-checkout-btn" type="button" id="copyBankAccount">
                        Salin Nomor Rekening
                    </button>
                    <p>Status pembayaran: {{ ucfirst($order->payment_status) }}</p>
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

@if ($order->payment_method === \App\Models\Order::PAYMENT_METHOD_BANK_TRANSFER)
    <script>
        document.getElementById('copyBankAccount')?.addEventListener('click', async () => {
            await navigator.clipboard.writeText(
                document.getElementById('bankAccountNumber').textContent.trim()
            );
        });
    </script>
@endif
