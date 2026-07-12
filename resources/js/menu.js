/**
 * Menu Page Behaviour
 * - Filter kategori (pill button) via AJAX ke /menu/filter
 * - Search dengan debounce
 * - Infinite pagination ("load more" otomatis saat scroll ke bawah, opsional)
 */

function debounce(fn, delay = 400) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), delay);
    };
}

async function fetchProducts({ category = 'semua', search = '', page = 1 } = {}) {
    const params = new URLSearchParams({ category, search, page });
    const response = await fetch(`/menu/filter?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (!response.ok) throw new Error('Gagal memuat menu');
    return response.json();
}

export function initMenuFilter() {
    const categoryBar = document.getElementById('categoryBar');
    const searchInput = document.getElementById('searchInput');
    const searchBtn = document.getElementById('searchSubmitBtn');
    const gridWrapper = document.querySelector('.product-grid')?.parentElement;

    if (!categoryBar && !searchInput) return;

    let state = {
        category: document.querySelector('.category-pill.is-active')?.dataset.category || 'semua',
        search: searchInput?.value || '',
        page: 1,
    };

    async function reload() {
        try {
            const data = await fetchProducts(state);
            const grid = document.getElementById('productGrid');
            if (grid) {
                grid.outerHTML = data.html;
            }
        } catch (error) {
            console.error(error);
        }
    }

    // Filter kategori
    categoryBar?.addEventListener('click', (e) => {
        const pill = e.target.closest('.category-pill');
        if (!pill) return;

        categoryBar.querySelectorAll('.category-pill').forEach((p) => p.classList.remove('is-active'));
        pill.classList.add('is-active');

        state.category = pill.dataset.category;
        state.page = 1;
        reload();

        // Update URL tanpa reload, agar bisa di-bookmark / share
        const url = new URL(window.location);
        url.searchParams.set('category', state.category);
        window.history.pushState({}, '', url);
    });

    // Search dengan debounce
    const debouncedSearch = debounce(() => {
        state.search = searchInput.value.trim();
        state.page = 1;
        reload();
    }, 400);

    searchInput?.addEventListener('input', debouncedSearch);
    searchBtn?.addEventListener('click', () => {
        state.search = searchInput.value.trim();
        state.page = 1;
        reload();
    });
}
