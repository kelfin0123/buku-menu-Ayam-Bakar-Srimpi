<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);
Route::post('products/upload-image', [ProductController::class, 'uploadImage']);
Route::post('products', [ProductController::class, 'store']);
Route::match(['put', 'post'], 'products/{product}', [ProductController::class, 'update']);
Route::delete('products/{product}', [ProductController::class, 'destroy']);

Route::prefix('orders')->group(function () {
    Route::get('pending', [OrderController::class, 'pending']);
    Route::post('{order}/accept', [OrderController::class, 'accept']);
    Route::post('{order}/reject', [OrderController::class, 'reject']);
    Route::get('employee-activities', [OrderController::class, 'employeeActivities']);
    Route::post('{order}/finish', [OrderController::class, 'finish']);
});

Route::prefix('v1')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('activities', [\App\Http\Controllers\Api\ActivityController::class, 'index']);
        Route::post('activities/{activity}/read', [\App\Http\Controllers\Api\ActivityController::class, 'markRead']);
    });
});
