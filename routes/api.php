<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\Owner\HeroBannerController;
use App\Http\Controllers\Api\Owner\MidtransSettingController;
use App\Http\Controllers\Api\Owner\PromotionController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PublicWebsiteContentController;
use Illuminate\Support\Facades\Route;

Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);
Route::get('hero-banners', [PublicWebsiteContentController::class, 'heroBanners']);
Route::get('promotions', [PublicWebsiteContentController::class, 'promotions']);
Route::post('products/upload', [ProductController::class, 'uploadImage']);
Route::post('products/upload-image', [ProductController::class, 'uploadImage']);
Route::post('products/sync', [ProductController::class, 'sync']);
Route::post('products', [ProductController::class, 'store']);
Route::match(['put', 'post'], 'products/{product}', [ProductController::class, 'update']);
Route::delete('products/{product}', [ProductController::class, 'destroy']);
Route::post('products/validate', [ProductController::class, 'validateProducts']);

Route::post('checkout', [CheckoutController::class, 'store']);

Route::middleware('firebase.staff')->group(function () {
    Route::post('device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('device-tokens/current', [DeviceTokenController::class, 'destroyCurrent']);
    Route::patch('orders/{order}/seen', [OrderController::class, 'markSeen']);
});

Route::prefix('orders')->group(function () {
    Route::get('code/{orderCode}', [OrderController::class, 'showByCode']);
    Route::get('incoming', [OrderController::class, 'incoming']);
    Route::post('{order}/accept', [OrderController::class, 'accept']);
    Route::post('{order}/reject', [OrderController::class, 'reject']);
    Route::get('activities', [OrderController::class, 'activities']);
    Route::post('{order}/ready', [OrderController::class, 'ready']);
    Route::post('{order}/complete', [OrderController::class, 'complete']);
    Route::get('statistics', [OrderController::class, 'statistics']);
});

Route::prefix('payment')->group(function () {
    Route::middleware('firebase.auth')->group(function () {
        Route::post('pos/charge', [PaymentController::class, 'chargePos']);
        Route::get('pos/{orderId}/status', [PaymentController::class, 'posStatus']);
    });
    Route::post('{order}/qris', [PaymentController::class, 'generateQris']);
    Route::get('{order}/status', [PaymentController::class, 'checkStatus']);
    Route::post('callback', [PaymentController::class, 'handleCallback']);
    Route::post('{order}/confirm-cash', [PaymentController::class, 'confirmCashPayment']);
});

Route::prefix('owner/payment-settings/midtrans')
    ->middleware('firebase.owner')
    ->group(function () {
        Route::get('/', [MidtransSettingController::class, 'show']);
        Route::put('/', [MidtransSettingController::class, 'update']);
        Route::post('/test', [MidtransSettingController::class, 'test']);
        Route::delete('/', [MidtransSettingController::class, 'destroy']);
    });

Route::middleware('firebase.owner')->prefix('owner')->group(function () {
    Route::patch('hero-banners/reorder', [HeroBannerController::class, 'reorder']);
    Route::patch('hero-banners/{heroBanner}/toggle', [HeroBannerController::class, 'toggle']);
    Route::post('hero-banners/{heroBanner}', [HeroBannerController::class, 'update']);
    Route::apiResource('hero-banners', HeroBannerController::class)
        ->parameters(['hero-banners' => 'heroBanner']);

    Route::patch('promotions/reorder', [PromotionController::class, 'reorder']);
    Route::patch('promotions/{promotion}/toggle', [PromotionController::class, 'toggle']);
    Route::post('promotions/{promotion}', [PromotionController::class, 'update']);
    Route::apiResource('promotions', PromotionController::class);
});

Route::prefix('v1')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('activities', [ActivityController::class, 'index']);
        Route::post('activities/{activity}/read', [ActivityController::class, 'markRead']);
        Route::post('orders/{order}/delivery-fee', [OrderController::class, 'confirmDeliveryFee']);
    });
});
