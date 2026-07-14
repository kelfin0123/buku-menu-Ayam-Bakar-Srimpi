<?php

use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\MenuController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Customer (Digital Menu Ayam Bakar Srimpi)
|--------------------------------------------------------------------------
*/

// ===============================
// HOME
// ===============================
Route::get('/', [MenuController::class, 'index'])->name('home');

// ===============================
// MENU
// ===============================
Route::prefix('menu')->name('menu.')->group(function () {

    Route::get('/', [MenuController::class, 'index'])->name('index');

    // AJAX Filter Menu
    Route::get('/filter', [MenuController::class, 'filter'])->name('filter');
});

// ===============================
// DETAIL PRODUK
// ===============================
Route::get('/product/{slug}', [ProductController::class, 'show'])->name('product.show');

// ===============================
// CHECKOUT
// ===============================
Route::prefix('checkout')->name('checkout.')->group(function () {

    // Halaman Checkout
    Route::get('/', [CheckoutController::class, 'index'])->name('index');

    // Simpan Pesanan
    Route::post('/', [CheckoutController::class, 'store'])->name('store');

    // Halaman Pembayaran
    Route::get('/payment/{orderCode}', [CheckoutController::class, 'payment'])->name('payment');

    // Cek Status Pembayaran (AJAX)
    Route::get('/status/{orderCode}', [CheckoutController::class, 'status'])->name('status');

    // Batalkan Pesanan
    Route::post('/cancel/{orderCode}', [CheckoutController::class, 'cancel'])->name('cancel');
});

// ===============================
// ORDER
// ===============================
Route::prefix('order')->name('order.')->group(function () {

    // Detail Pesanan
    Route::get('/{orderCode}', [OrderController::class, 'show'])->name('show');

});

// ===============================
// API PRODUK (UNTUK MOBILE)
// ===============================
Route::get('/produk', function () {

    return response()->json(
        \App\Models\Product::active()->get()->map(function ($product) {

            return [
                'id' => (int) $product->id,
                'nama' => $product->name,
                'harga' => (int) $product->price,
                'gambar' => $product->image,
            ];

        })
    );

});