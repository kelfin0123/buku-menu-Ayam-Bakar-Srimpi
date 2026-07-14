@extends('layouts.app')

@section('title', 'Pembayaran - Ayam Bakar Srimpi')

@section('content')
    <header class="topbar">
        <h1 class="checkout-heading">Pembayaran</h1>
    </header>

    <section class="payment-wrap">
        <div id="paymentContent">
            <!-- Payment content will be loaded here -->
            <div class="loading-spinner">Memuat...</div>
        </div>
    </section>

    <script>
        const orderCode = '{{ $orderCode }}';
        const API_URL = '/api';

        // Load payment data
        async function loadPaymentData() {
            try {
                const response = await fetch(`${API_URL}/orders/code/${orderCode}`);
                const data = await response.json();
                
                if (data.success && data.data) {
                    const order = data.data;
                    renderPaymentPage(order);
                } else {
                    showError('Pesanan tidak ditemukan');
                }
            } catch (error) {
                showError('Gagal memuat data pembayaran');
            }
        }

        function renderPaymentPage(order) {
            const paymentContent = document.getElementById('paymentContent');
            
            if (order.payment_method === 'cash') {
                renderCashPayment(order);
            } else if (order.payment_method === 'qris') {
                renderQrisPayment(order);
            }
        }

        function renderCashPayment(order) {
            const paymentContent = document.getElementById('paymentContent');
            paymentContent.innerHTML = `
                <div class="payment-card">
                    <div class="payment-header">
                        <h2>Pembayaran Tunai</h2>
                        <p class="order-code">Order: ${order.order_code}</p>
                    </div>
                    
                    <div class="payment-amount">
                        <p class="amount-label">Total Pembayaran</p>
                        <p class="amount-value">Rp ${formatNumber(order.total)}</p>
                    </div>
                    
                    <div class="payment-info">
                        <div class="info-row">
                            <span>Nama Customer:</span>
                            <span>${order.customer_name}</span>
                        </div>
                        <div class="info-row">
                            <span>Nomor Meja:</span>
                            <span>${order.table_number}</span>
                        </div>
                    </div>
                    
                    <div class="countdown-container">
                        <p class="countdown-label">Waktu Pembayaran:</p>
                        <div id="countdown" class="countdown-timer">05:00</div>
                    </div>
                    
                    <div class="payment-status waiting">
                        <span class="status-icon">⏳</span>
                        <span>Menunggu Pembayaran Tunai</span>
                    </div>
                    
                    <div class="payment-note">
                        <p>Silakan bayar ke kasir. Pembayaran akan dikonfirmasi oleh employee.</p>
                    </div>
                    
                    <div class="order-items">
                        <h3>Ringkasan Pesanan</h3>
                        ${order.items.map(item => `
                            <div class="order-item-row">
                                <span>${item.product_name} x${item.qty}</span>
                                <span>Rp ${formatNumber(item.subtotal)}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
            startCountdown(order.expires_at);
            startPaymentStatusCheck(order.id);
        }

        function renderQrisPayment(order) {
            const paymentContent = document.getElementById('paymentContent');
            
            // Generate QRIS
            generateQris(order);
        }

        async function generateQris(order) {
            try {
                const response = await fetch(`${API_URL}/payment/${order.id}/qris`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                });
                
                const data = await response.json();
                
                if (data.success) {
                    renderQrisWithToken(order, data.data.snap_token);
                } else {
                    showError(data.message || 'Gagal membuat QRIS');
                }
            } catch (error) {
                showError('Gagal membuat pembayaran QRIS');
            }
        }

        function renderQrisWithToken(order, snapToken) {
            const paymentContent = document.getElementById('paymentContent');
            paymentContent.innerHTML = `
                <div class="payment-card">
                    <div class="payment-header">
                        <h2>Pembayaran QRIS</h2>
                        <p class="order-code">Order: ${order.order_code}</p>
                    </div>
                    
                    <div class="qris-container">
                        <div id="qris-qr" class="qris-qr"></div>
                    </div>
                    
                    <div class="payment-amount">
                        <p class="amount-label">Total Pembayaran</p>
                        <p class="amount-value">Rp ${formatNumber(order.total)}</p>
                    </div>
                    
                    <div class="countdown-container">
                        <p class="countdown-label">Waktu Pembayaran:</p>
                        <div id="countdown" class="countdown-timer">05:00</div>
                    </div>
                    
                    <div class="payment-status waiting">
                        <span class="status-icon">⏳</span>
                        <span>Menunggu Pembayaran QRIS</span>
                    </div>
                    
                    <div class="payment-note">
                        <p>Scan QR code di atas menggunakan aplikasi e-wallet atau mobile banking Anda.</p>
                        <p>Pembayaran akan otomatis dikonfirmasi.</p>
                    </div>
                    
                    <div class="order-items">
                        <h3>Ringkasan Pesanan</h3>
                        ${order.items.map(item => `
                            <div class="order-item-row">
                                <span>${item.product_name} x${item.qty}</span>
                                <span>Rp ${formatNumber(item.subtotal)}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            
            // Load Midtrans Snap
            loadMidtransSnap(snapToken);
            startCountdown(order.expires_at);
            startPaymentStatusCheck(order.id);
        }

        function loadMidtransSnap(snapToken) {
            const script = document.createElement('script');
            script.src = 'https://app.sandbox.midtrans.com/snap/snap.js';
            script.setAttribute('data-client-key', 'SB-Mid-client-TEST');
            script.onload = () => {
                window.snap.pay(snapToken, {
                    onSuccess: function(result) {
                        alert('Pembayaran berhasil!');
                        location.reload();
                    },
                    onPending: function(result) {
                        console.log('Pembayaran pending');
                    },
                    onError: function(result) {
                        alert('Pembayaran gagal');
                    }
                });
            };
            document.body.appendChild(script);
        }

        function startCountdown(expiresAt) {
            const countdownElement = document.getElementById('countdown');
            if (!countdownElement) return;
            
            const expiryTime = new Date(expiresAt).getTime();
            
            const interval = setInterval(() => {
                const now = new Date().getTime();
                const distance = expiryTime - now;
                
                if (distance < 0) {
                    clearInterval(interval);
                    countdownElement.innerHTML = '00:00';
                    showExpired();
                    return;
                }
                
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                countdownElement.innerHTML = 
                    (minutes < 10 ? '0' : '') + minutes + ':' + 
                    (seconds < 10 ? '0' : '') + seconds;
            }, 1000);
        }

        function startPaymentStatusCheck(orderId) {
            const interval = setInterval(async () => {
                try {
                    const response = await fetch(`${API_URL}/payment/${orderId}/status`);
                    const data = await response.json();
                    
                    if (data.success) {
                        if (data.data.payment_status === 'paid') {
                            clearInterval(interval);
                            showPaymentSuccess();
                        } else if (data.data.is_expired) {
                            clearInterval(interval);
                            showExpired();
                        }
                    }
                } catch (error) {
                    console.error('Error checking payment status:', error);
                }
            }, 3000); // Check every 3 seconds
        }

        function showPaymentSuccess() {
            const paymentContent = document.getElementById('paymentContent');
            paymentContent.innerHTML = `
                <div class="payment-card success">
                    <div class="success-icon">✓</div>
                    <h2>Pembayaran Berhasil!</h2>
                    <p>Pesanan Anda sedang diproses.</p>
                    <p class="order-code">Order: ${orderCode}</p>
                    <a href="/" class="btn-primary">Kembali ke Menu</a>
                </div>
            `;
        }

        function showExpired() {
            const paymentContent = document.getElementById('paymentContent');
            paymentContent.innerHTML = `
                <div class="payment-card expired">
                    <div class="expired-icon">⏰</div>
                    <h2>Pembayaran Kadaluarsa</h2>
                    <p>Waktu pembayaran telah habis.</p>
                    <p class="order-code">Order: ${orderCode}</p>
                    <a href="/" class="btn-primary">Kembali ke Menu</a>
                </div>
            `;
        }

        function showError(message) {
            const paymentContent = document.getElementById('paymentContent');
            paymentContent.innerHTML = `
                <div class="payment-card error">
                    <div class="error-icon">✕</div>
                    <h2>Terjadi Kesalahan</h2>
                    <p>${message}</p>
                    <a href="/" class="btn-primary">Kembali ke Menu</a>
                </div>
            `;
        }

        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Load payment data on page load
        document.addEventListener('DOMContentLoaded', loadPaymentData);
    </script>

    <style>
        .payment-wrap {
            max-width: 600px;
            margin: 0 auto;
            padding: 1rem;
        }
        
        .payment-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .payment-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .payment-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .order-code {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .payment-amount {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #fef3c7;
            border-radius: 0.5rem;
        }
        
        .amount-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .amount-value {
            font-size: 2rem;
            font-weight: 700;
            color: #f59e0b;
        }
        
        .payment-info {
            margin-bottom: 2rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .qris-container {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .qris-qr {
            min-height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9fafb;
            border-radius: 0.5rem;
        }
        
        .countdown-container {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .countdown-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .countdown-timer {
            font-size: 2.5rem;
            font-weight: 700;
            color: #ef4444;
        }
        
        .payment-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .payment-status.waiting {
            background: #fef3c7;
            color: #92400e;
        }
        
        .payment-status.paid {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-icon {
            font-size: 1.5rem;
        }
        
        .payment-note {
            background: #f3f4f6;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .order-items {
            margin-top: 2rem;
        }
        
        .order-items h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .order-item-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .order-item-row:last-child {
            border-bottom: none;
        }
        
        .payment-card.success {
            text-align: center;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #10b981;
            margin-bottom: 1rem;
        }
        
        .payment-card.expired {
            text-align: center;
        }
        
        .expired-icon {
            font-size: 4rem;
            color: #ef4444;
            margin-bottom: 1rem;
        }
        
        .payment-card.error {
            text-align: center;
        }
        
        .error-icon {
            font-size: 4rem;
            color: #ef4444;
            margin-bottom: 1rem;
        }
        
        .btn-primary {
            display: inline-block;
            padding: 0.75rem 2rem;
            background: #f59e0b;
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 600;
            margin-top: 1.5rem;
        }
        
        .loading-spinner {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
        }
    </style>
@endsection
