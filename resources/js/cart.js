/**
 * Cart Management
 * Menyimpan isi keranjang di localStorage supaya tetap ada walau halaman di-refresh.
 * Struktur item: { id, name, price, image, qty }
 */

const CART_KEY = 'ayam_bakar_srimpi_cart';
const SHIPPING_COST = 5000;

function getCart() {
    try {
        return JSON.parse(localStorage.getItem(CART_KEY)) || [];
    } catch (e) {
        return [];
    }
}

function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
    renderCart();
    updateTopbarCount();
}

/**
 * Validate cart items against database
 * Remove items that no longer exist in database
 */
async function validateCart() {
    const cart = getCart();
    if (cart.length === 0) return cart;

    try {
        const productIds = cart.map(item => item.id);
        const response = await fetch('/api/products/validate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ product_ids: productIds })
        });

        const result = await response.json();

        if (!result.valid && result.invalid_ids && result.invalid_ids.length > 0) {
            // Remove invalid products from cart
            const validCart = cart.filter(item => result.valid_ids.includes(item.id));
            localStorage.setItem(CART_KEY, JSON.stringify(validCart));

            // Show notification to user
            const invalidCount = result.invalid_ids.length;
            if (invalidCount > 0) {
                // Use a subtle notification instead of alert
                showCartNotification(`${invalidCount} produk di keranjang tidak tersedia lagi dan telah dihapus.`);
            }

            return validCart;
        }

        return cart;
    } catch (error) {
        console.error('Error validating cart:', error);
        return cart; // Return original cart if validation fails
    }
}

function showCartNotification(message) {
    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #fef3c7;
        color: #92400e;
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        z-index: 9999;
        font-size: 14px;
        border-left: 4px solid #f59e0b;
        animation: slideIn 0.3s ease-out;
    `;
    notification.textContent = message;

    // Add animation keyframes
    if (!document.getElementById('cart-notification-style')) {
        const style = document.createElement('style');
        style.id = 'cart-notification-style';
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }

    document.body.appendChild(notification);

    // Remove after 5 seconds
    setTimeout(() => {
        notification.style.animation = 'slideIn 0.3s ease-out reverse';
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

function formatRupiah(number) {
    return 'Rp ' + Number(number).toLocaleString('id-ID');
}

function addToCart(product) {
    const cart = getCart();
    const existing = cart.find((item) => item.id === product.id);

    if (existing) {
        existing.qty += 1;
    } else {
        cart.push({ ...product, qty: 1 });
    }

    saveCart(cart);
}

function increaseQty(id) {
    const cart = getCart();
    const item = cart.find((i) => i.id === id);
    if (item) item.qty += 1;
    saveCart(cart);
}

function decreaseQty(id) {
    let cart = getCart();
    const item = cart.find((i) => i.id === id);

    if (item) {
        item.qty -= 1;
        if (item.qty <= 0) {
            cart = cart.filter((i) => i.id !== id);
        }
    }

    saveCart(cart);
}

function removeItem(id) {
    const cart = getCart().filter((i) => i.id !== id);
    saveCart(cart);
}

function clearCart() {
    saveCart([]);
}

function calculateSubtotal(cart) {
    return cart.reduce((sum, item) => sum + item.price * item.qty, 0);
}

function updateTopbarCount() {
    const countEl = document.getElementById('topbarCartCount');
    if (!countEl) return;
    const totalQty = getCart().reduce((sum, item) => sum + item.qty, 0);
    countEl.textContent = totalQty;
}

/**
 * Render ulang seluruh isi cart panel berdasarkan data di localStorage.
 */
function renderCart() {
    const container = document.getElementById('cartItems');
    const emptyState = document.getElementById('cartEmptyState');
    const template = document.getElementById('cartItemTemplate');
    const subtotalEl = document.getElementById('cartSubtotal');
    const shippingEl = document.getElementById('cartShipping');
    const totalEl = document.getElementById('cartTotal');
    const checkoutBtn = document.getElementById('cartCheckoutBtn');

    if (!container || !template) return;

    const cart = getCart();

    // Bersihkan item lama (kecuali empty-state)
    container.querySelectorAll('[data-cart-item]').forEach((el) => el.remove());

    if (cart.length === 0) {
        if (emptyState) emptyState.style.display = 'block';
    } else {
        if (emptyState) emptyState.style.display = 'none';

        cart.forEach((item) => {
            const node = template.content.cloneNode(true);
            const root = node.querySelector('[data-cart-item]');

            root.dataset.id = item.id;
            node.querySelector('[data-role="image"]').src = item.image;
            node.querySelector('[data-role="image"]').alt = item.name;
            node.querySelector('[data-role="name"]').textContent = item.name;
            node.querySelector('[data-role="qty"]').textContent = item.qty;
            node.querySelector('[data-role="price"]').textContent = formatRupiah(item.price * item.qty);

            node.querySelector('[data-role="increase"]').addEventListener('click', () => increaseQty(item.id));
            node.querySelector('[data-role="decrease"]').addEventListener('click', () => decreaseQty(item.id));
            node.querySelector('[data-role="remove"]').addEventListener('click', () => removeItem(item.id));

            container.appendChild(node);
        });
    }

    const subtotal = calculateSubtotal(cart);
    const shipping = cart.length > 0 ? SHIPPING_COST : 0;
    const total = subtotal + shipping;

    if (subtotalEl) subtotalEl.textContent = formatRupiah(subtotal);
    if (shippingEl) shippingEl.textContent = formatRupiah(shipping);
    if (totalEl) totalEl.textContent = formatRupiah(total);
    if (checkoutBtn) checkoutBtn.disabled = cart.length === 0;
}

export function initCart() {
    // Validate cart on initialization
    validateCart().then(validatedCart => {
        renderCart();
        updateTopbarCount();
    });

    // Tombol "+" pada tiap product card
    document.body.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-add-to-cart]');
        if (!btn) return;

        addToCart({
            id: parseInt(btn.dataset.id, 10),
            name: btn.dataset.name,
            price: parseInt(btn.dataset.price, 10),
            image: btn.dataset.image,
        });

        // micro-interaction: tandai tombol sudah ditambahkan sebentar
        btn.classList.add('is-added');
        setTimeout(() => btn.classList.remove('is-added'), 600);
    });

    document.getElementById('cartClearBtn')?.addEventListener('click', () => {
        if (confirm('Hapus semua pesanan?')) clearCart();
    });

    document.getElementById('cartCheckoutBtn')?.addEventListener('click', () => {
        if (getCart().length === 0) return;
        window.location.href = '/checkout';
    });

    document.getElementById('viewOrdersBtn')?.addEventListener('click', () => {
        document.getElementById('cartPanel')?.scrollIntoView({ behavior: 'smooth' });
    });
}

// Diekspor agar bisa dipakai halaman checkout untuk mengisi ringkasan & hidden input
export { getCart, calculateSubtotal, formatRupiah, SHIPPING_COST, validateCart, clearCart };
