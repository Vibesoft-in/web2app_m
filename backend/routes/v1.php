<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AppConfigController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API Routes (/v1/*)
|--------------------------------------------------------------------------
| Headers:
|   - X-App-Key: resolves app and tenant, applies app+IP throttle
|   - Authorization: Bearer <token> (for authenticated customer endpoints)
*/

Route::middleware(['app.key'])->group(function () {
    // ── Public Store Endpoints (guarded by X-App-Key) ─────────────────────────
    Route::get('/app-config', [AppConfigController::class, 'show'])->name('app-config');
    Route::get('/products', [CatalogController::class, 'products'])->name('products');
    Route::get('/products/{id}', [CatalogController::class, 'show'])->name('products.show');
    Route::get('/categories', [CatalogController::class, 'categories'])->name('categories');

    // ── Customer Authentication ───────────────────────────────────────────────
    Route::post('/auth/otp/request', [AuthController::class, 'requestOtp'])->name('auth.otp.request');
    Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])->name('auth.otp.verify');
    Route::post('/auth/social/google', [AuthController::class, 'socialGoogle'])->name('auth.google');

    // ── Cart & Checkout Revalidation ───────────────────────────────────────────
    Route::post('/cart/checkout-url', [CartController::class, 'checkoutUrl'])->name('cart.checkout-url');

    // ── Authenticated Customer Routes (scoped token required) ──────────────────
    Route::middleware(['auth:sanctum', 'app.user'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::delete('/account', [AuthController::class, 'deleteAccount'])->name('auth.account.delete');

        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');

        Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::delete('/devices', [DeviceController::class, 'destroy'])->name('devices.destroy');
    });
});
