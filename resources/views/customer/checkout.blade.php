@extends('layouts.app')

@section('title', 'Checkout - Ayam Bakar Srimpi')

@section('content')
    <header class="topbar">
        <h1 class="checkout-heading">Checkout</h1>
    </header>

    <section class="checkout-wrap">
        <form id="checkoutForm" class="checkout-form" method="POST" action="{{ route('checkout.store') }}">
            @csrf
            <div class="checkout-field">
                <label for="customer_name">Nama Penerima</label>
                <input type="text" name="customer_name" id="customer_name" required>
            </div>
            <div class="checkout-field">
                <label for="customer_phone">No. WhatsApp</label>
                <input type="text" name="customer_phone" id="customer_phone" required>
            </div>
            <div class="checkout-field">
                <label for="customer_address">Alamat Pengiriman</label>
                <textarea name="customer_address" id="customer_address" rows="3"></textarea>
            </div>

            {{-- Ringkasan diambil dari localStorage lewat cart.js lalu disuntikkan sebagai hidden input --}}
            <input type="hidden" name="items" id="checkoutItemsInput">

            <div id="checkoutSummary" class="checkout-summary"></div>

            <button type="submit" class="cart-checkout-btn">
                Bayar Sekarang
                <span>@include('components.icons.send')</span>
            </button>
            <p class="checkout-note">*Integrasi pembayaran Midtrans akan ditambahkan pada tahap berikutnya.</p>
        </form>
    </section>
@endsection
