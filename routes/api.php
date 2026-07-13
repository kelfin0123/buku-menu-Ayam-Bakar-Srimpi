<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public read-only routes could be added (index/show) if desired

    // Protected CRUD for products (Owner/Employee) - requires auth:sanctum
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('products', [ProductController::class, 'store']);
        Route::post('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
        // Activities for employees
        Route::get('activities', [\App\Http\Controllers\Api\ActivityController::class, 'index']);
        Route::post('activities/{activity}/read', [\App\Http\Controllers\Api\ActivityController::class, 'markRead']);
    });
});
