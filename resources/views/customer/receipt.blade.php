@extends('layouts.app')

@section('title', 'Nota ' . $order->order_code)

@section('content')
    <header class="topbar">
        <h1 class="checkout-heading">Nota Digital</h1>
    </header>

    <section class="order-wrap">
        <article class="order-card">
            <p class="order-code">{{ $order->order_code }}</p>
            <p>{{ $order->created_at->format('d/m/Y H:i') }}</p>
            <p>{{ $order->customer_name }}</p>

            <div class="order-items">
                @foreach ($order->items as $item)
                    <div class="order-item-row">
                        <span>{{ $item->qty }}x {{ $item->product_name }}</span>
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
                    <span>Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                </div>
            </div>

            <p class="order-status order-status-{{ $order->status }}">
                {{ ucfirst(str_replace('_', ' ', $order->status)) }}
            </p>

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
        </article>
    </section>
@endsection
