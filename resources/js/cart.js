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

    renderCart();
    updateTopbarCount();
}

// Diekspor agar bisa dipakai halaman checkout untuk mengisi ringkasan & hidden input
export { getCart, calculateSubtotal, formatRupiah, SHIPPING_COST };
