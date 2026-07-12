/**
 * Hero Slider
 * - Auto slide setiap N ms (data-autoplay pada #heroSlider)
 * - Tombol Previous / Next
 * - Indicator (dots) yang bisa diklik langsung
 */

export function initHeroSlider() {
    const root = document.getElementById('heroSlider');
    if (!root) return;

    const slides = Array.from(root.querySelectorAll('.hero-slide'));
    const dots = Array.from(root.querySelectorAll('.hero-dot'));
    const prevBtn = document.getElementById('heroPrev');
    const nextBtn = document.getElementById('heroNext');

    if (slides.length === 0) return;

    let current = slides.findIndex((s) => s.classList.contains('is-active'));
    if (current < 0) current = 0;

    const autoplayDelay = parseInt(root.dataset.autoplay, 10) || 5000;
    let timer = null;

    function goTo(index) {
        const total = slides.length;
        const nextIndex = (index + total) % total;

        slides[current].classList.remove('is-active');
        dots[current]?.classList.remove('is-active');

        current = nextIndex;

        slides[current].classList.add('is-active');
        dots[current]?.classList.add('is-active');
    }

    function next() {
        goTo(current + 1);
    }

    function prev() {
        goTo(current - 1);
    }

    function startAutoplay() {
        stopAutoplay();
        timer = setInterval(next, autoplayDelay);
    }

    function stopAutoplay() {
        if (timer) clearInterval(timer);
    }

    prevBtn?.addEventListener('click', () => {
        prev();
        startAutoplay(); // reset timer supaya tidak langsung geser lagi
    });

    nextBtn?.addEventListener('click', () => {
        next();
        startAutoplay();
    });

    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            goTo(parseInt(dot.dataset.index, 10));
            startAutoplay();
        });
    });

    // Pause saat hover, lanjut lagi saat mouse keluar
    root.addEventListener('mouseenter', stopAutoplay);
    root.addEventListener('mouseleave', startAutoplay);

    startAutoplay();
}
