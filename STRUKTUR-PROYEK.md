# Struktur Proyek — Ayam Bakar Srimpi (Digital Menu)

```
ayam-bakar-srimpi/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── Customer/
│   │           ├── MenuController.php
│   │           ├── ProductController.php
│   │           ├── CheckoutController.php
│   │           └── OrderController.php
│   └── Models/
│       ├── Category.php
│       ├── Product.php
│       ├── Order.php
│       └── OrderItem.php
│
├── database/
│   └── migrations/
│       ├── 2024_01_01_000001_create_categories_table.php
│       ├── 2024_01_01_000002_create_products_table.php
│       ├── 2024_01_01_000003_create_orders_table.php
│       └── 2024_01_01_000004_create_order_items_table.php
│
├── database/seeders/
│   ├── CategorySeeder.php
│   ├── ProductSeeder.php
│   └── DatabaseSeeder.php
│
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   └── app.blade.php
│   │   ├── customer/
│   │   │   ├── menu.blade.php
│   │   │   ├── checkout.blade.php
│   │   │   └── order.blade.php
│   │   └── components/
│   │       ├── sidebar.blade.php
│   │       ├── hero.blade.php
│   │       ├── category.blade.php
│   │       ├── product-card.blade.php
│   │       └── cart.blade.php
│   ├── css/
│   │   └── app.css
│   └── js/
│       ├── app.js
│       ├── menu.js
│       ├── cart.js
│       └── slider.js
│
├── routes/
│   └── web.php
│
├── public/
│   └── images/
│       ├── logo/
│       ├── products/
│       ├── banner/
│       └── icons/
│
├── vite.config.js
├── tailwind.config.js
└── postcss.config.js
```

> Catatan: file-file di paket ini adalah source code (Model, Controller, Migration, Blade,
> CSS, JS) yang harus ditempel ke dalam project Laravel 12 yang sudah di-generate dengan
> `composer create-project laravel/laravel`. Lihat file `CARA-MENJALANKAN.md` untuk langkah
> instalasi lengkap (composer & npm perlu koneksi internet di komputer Anda, sehingga
> proses scaffolding awal harus dijalankan di mesin lokal Anda).
