import '../css/app.css';

import { initHeroSlider } from './slider.js';
import { initMenuFilter } from './menu.js';
import { initCart } from './cart.js';

/**
 * Dark Mode Toggle
 * Preferensi disimpan di localStorage (key: "theme") supaya persist
 * antar halaman & saat browser ditutup.
 */
const MOON_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg>';
const SUN_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>';

function initDarkMode() {
    const toggleBtn = document.getElementById('darkModeToggle');
    const iconWrap = document.getElementById('darkModeIcon');
    if (!toggleBtn) return;

    function applyIcon() {
        const isDark = document.documentElement.classList.contains('dark');
        if (iconWrap) iconWrap.innerHTML = isDark ? SUN_ICON : MOON_ICON;
        toggleBtn.querySelector('span:last-child').textContent = isDark ? 'Mode Terang' : 'Mode Gelap';
    }

    toggleBtn.addEventListener('click', () => {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        applyIcon();
    });

    applyIcon();
}

document.addEventListener('DOMContentLoaded', () => {
    initDarkMode();
    initHeroSlider();
    initMenuFilter();
    initCart();
});
