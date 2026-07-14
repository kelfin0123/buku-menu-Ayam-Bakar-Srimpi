/**
 * Checkout Management
 * Handles checkout form submission to API and redirects to payment page
 */

const API_URL = '/api';

export function initCheckout() {
    const checkoutForm = document.getElementById('checkoutForm');
    if (!checkoutForm) return;

    checkoutForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const submitBtn = checkoutForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Get cart data
        const cart = JSON.parse(localStorage.getItem('ayam_bakar_srimpi_cart')) || [];
        
        if (cart.length === 0) {
            alert('Keranjang kosong!');
            return;
        }
        
        // Prepare items data
        const items = cart.map(item => ({
            product_id: item.id,
            qty: item.qty
        }));
        
        // Get form data
        const formData = new FormData(checkoutForm);
        const checkoutData = {
            customer_name: formData.get('customer_name'),
            table_number: formData.get('table_number'),
            payment_method: formData.get('payment_method'),
            items: items
        };
        
        // Validate
        if (!checkoutData.customer_name || !checkoutData.table_number || !checkoutData.payment_method) {
            alert('Mohon lengkapi semua data!');
            return;
        }
        
        // Disable button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>Memproses...</span>';
        
        try {
            const response = await fetch(`${API_URL}/checkout`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(checkoutData),
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Clear cart
                localStorage.removeItem('ayam_bakar_srimpi_cart');
                
                // Redirect to payment page
                window.location.href = `/checkout/payment/${data.data.order_code}`;
            } else {
                alert(data.message || 'Terjadi kesalahan saat membuat pesanan');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        } catch (error) {
            console.error('Checkout error:', error);
            alert('Terjadi kesalahan koneksi. Silakan coba lagi.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    // Render checkout summary
    renderCheckoutSummary();
}

function renderCheckoutSummary() {
    const summaryContainer = document.getElementById('checkoutSummary');
    if (!summaryContainer) return;
    
    const cart = JSON.parse(localStorage.getItem('ayam_bakar_srimpi_cart')) || [];
    
    if (cart.length === 0) {
        summaryContainer.innerHTML = '<p class="empty-cart">Keranjang kosong</p>';
        return;
    }
    
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

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initCheckout);
