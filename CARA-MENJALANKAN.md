# Cara Menghubungkan & Menjalankan Proyek

Paket ini berisi **source code** (migration, model, controller, route, blade,
css, js). Karena proses instalasi Laravel (`composer create-project`) dan
`npm install` membutuhkan koneksi internet di komputer Anda, langkah di bawah
harus dijalankan **di mesin lokal Anda**, bukan di lingkungan chat ini.

## 1. Buat project Laravel 12 baru

```bash
composer create-project laravel/laravel ayam-bakar-srimpi
cd ayam-bakar-srimpi
```

## 2. Salin semua file dari paket ini ke project

Timpa/salin folder & file berikut persis sesuai struktur yang sama:

```
app/Models/Category.php
app/Models/Product.php
app/Models/Order.php
app/Models/OrderItem.php
app/Http/Controllers/Customer/MenuController.php
app/Http/Controllers/Customer/ProductController.php
app/Http/Controllers/Customer/CheckoutController.php
app/Http/Controllers/Customer/OrderController.php

database/migrations/*.php
database/seeders/*.php

resources/views/layouts/app.blade.php
resources/views/customer/*.blade.php
resources/views/customer/partials/*.blade.php
resources/views/components/*.blade.php
resources/views/components/icons/*.blade.php

resources/css/app.css
resources/js/app.js
resources/js/menu.js
resources/js/cart.js
resources/js/slider.js

routes/web.php
vite.config.js
tailwind.config.js
postcss.config.js
```

> `routes/web.php` dan `vite.config.js` **menimpa** file default bawaan Laravel.

## 3. Daftarkan Blade Component

Laravel 12 otomatis mengenali komponen di `resources/views/components/`
sebagai `<x-nama-file />` — tidak perlu registrasi manual. Pastikan penamaan
file cocok:

- `components/sidebar.blade.php` → `<x-sidebar />`
- `components/hero.blade.php` → `<x-hero />`
- `components/category.blade.php` → `<x-category :categories="..." :active="..." />`
- `components/product-card.blade.php` → `<x-product-card :product="$product" />`
- `components/cart.blade.php` → `<x-cart />`

## 4. Install dependency Tailwind & Vite

```bash
npm install
npm install -D tailwindcss postcss autoprefixer laravel-vite-plugin
```

## 5. Setup database (`.env`)

```env
DB_CONNECTION=mysql
DB_DATABASE=ayam_bakar_srimpi
DB_USERNAME=root
DB_PASSWORD=
```

Buat database `ayam_bakar_srimpi` lalu jalankan:

```bash
php artisan migrate --seed
```

Ini akan membuat tabel `categories`, `products`, `orders`, `order_items`
sekaligus mengisi data kategori & produk contoh (sesuai desain: Ayam Bakar
Srimpi, Ayam Penyet, Nasi Goreng Srimpi, Es Teh Manis, dll).

## 6. Siapkan gambar produk & banner

Masukkan file gambar ke:

```
public/images/products/ayam-bakar-srimpi.jpg
public/images/products/ayam-penyet.jpg
public/images/products/ayam-goreng.jpg
public/images/products/nasi-goreng-srimpi.jpg
public/images/products/es-teh-manis.jpg
public/images/products/es-jeruk.jpg
public/images/products/es-lemon-tea.jpg
public/images/products/air-mineral.jpg

public/images/banner/hero-1.jpg
public/images/banner/hero-2.jpg
public/images/banner/hero-3.jpg

public/images/icons/delivery-scooter.png   (opsional, untuk ilustrasi Gratis Ongkir)
```

Jika file belum tersedia, tampilan tetap berjalan karena setiap `<img>` sudah
punya fallback (`onerror`) ke gambar placeholder online.

## 7. Jalankan proyek

Buka **dua terminal**:

```bash
# Terminal 1 - compile asset (Tailwind + JS) & watch perubahan
npm run dev
```

```bash
# Terminal 2 - jalankan server Laravel
php artisan serve
```

Buka `http://127.0.0.1:8000` — halaman Beranda/Menu akan tampil sesuai desain:
sidebar gelap dengan menu & banner Gratis Ongkir, hero slider otomatis,
kategori pill, grid produk, dan cart sticky di kanan.

## 8. Build untuk production

```bash
npm run build
```

## Ringkasan alur data (tanpa hardcode)

- `MenuController@index` mengambil `Category` & `Product` dari database lalu
  mengirim ke `customer/menu.blade.php`.
- `resources/views/customer/menu.blade.php` melakukan `@foreach` ke komponen
  `<x-category>` dan partial `product-grid` yang berisi `<x-product-card>`.
- Filter kategori & pencarian (`menu.js`) memanggil `MenuController@filter`
  (`/menu/filter`) via `fetch()`, lalu Laravel me-render ulang partial
  `customer/partials/product-grid.blade.php` dan mengembalikannya sebagai
  potongan HTML (server-side rendering, bukan hardcode di JS).
- Keranjang (`cart.js`) disimpan sementara di `localStorage` di sisi browser,
  lalu saat "Pesan Sekarang" ditekan, diarahkan ke `/checkout` untuk dikirim
  ke `CheckoutController@store` yang menyimpan `Order` + `OrderItem` ke
  database (harga produk selalu diambil ulang dari tabel `products`, bukan
  dari input klien, untuk mencegah manipulasi harga).
- Integrasi Midtrans Snap (token, callback notifikasi) belum ditambahkan —
  sesuai permintaan, tahap ini baru redirect ke halaman checkout.
