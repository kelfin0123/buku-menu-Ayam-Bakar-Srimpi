@extends('layouts.app')

@section('title', 'Pesanan ' . $order->order_code)

@section('content')
    <header class="topbar">
        <h1 class="checkout-heading">Detail Pesanan</h1>
    </header>

    <section class="order-wrap">
        <div class="order-card">
            <p class="order-code">{{ $order->order_code }}</p>
            <p class="order-status order-status-{{ $order->status }}">{{ ucfirst($order->status) }}</p>

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
                <div class="cart-summary-row">
                    <span>Ongkir</span>
                    <span>Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
                </div>
                <div class="cart-summary-row cart-summary-total">
                    <span>Total</span>
                    <span>Rp {{ number_format($order->total, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </section>
@endsection
