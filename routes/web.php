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

Route::get('/', [MenuController::class, 'index'])->name('home');

Route::prefix('menu')->name('menu.')->group(function () {
    Route::get('/', [MenuController::class, 'index'])->name('index');
    Route::get('/filter', [MenuController::class, 'filter'])->name('filter'); // AJAX
});

Route::get('/product/{slug}', [ProductController::class, 'show'])->name('product.show');

Route::prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/', [CheckoutController::class, 'index'])->name('index');
    Route::post('/', [CheckoutController::class, 'store'])->name('store');
});

Route::get('/order/{orderCode}', [OrderController::class, 'show'])->name('order.show');

Route::get('/produk', function () {
    return response()->json(
        \App\Models\Product::active()->get()->map(function ($product) {
            return [
                'id' => intval($product->id),
                'nama' => $product->name,
                'harga' => intval($product->price),
            ];
        })
    );
});
