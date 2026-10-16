<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook Routes (/webhooks/*)
|--------------------------------------------------------------------------
| No Sanctum auth — verified via HMAC signature in each controller.
| CSRF excluded in bootstrap/app.php.
| Phase 1+ will add: Razorpay, WooCommerce, Shopify handlers.
*/

// Razorpay webhooks (Billing & Subscriptions)
Route::post('/razorpay', [\App\Http\Controllers\Webhooks\RazorpayWebhookController::class, 'handle'])
    ->name('webhooks.razorpay');

// WooCommerce webhooks (Phase 1)
Route::post('/woo/{appUlid}', [\App\Http\Controllers\Webhooks\WooWebhookController::class, 'handle'])
    ->name('webhooks.woo');

// WhatsApp Cloud API webhooks (verification challenge and delivery events)
Route::get('/whatsapp', [\App\Http\Controllers\Webhooks\WhatsAppWebhookController::class, 'verify'])
    ->name('webhooks.whatsapp.verify');
Route::post('/whatsapp', [\App\Http\Controllers\Webhooks\WhatsAppWebhookController::class, 'handle'])
    ->name('webhooks.whatsapp.handle');

