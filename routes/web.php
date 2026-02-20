<?php

use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

// Payment webhook (no locale - BML posts to fixed URL)
Route::post('payments/bml/callback', [\App\Http\Controllers\PaymentController::class, 'callback'])
    ->name('payments.bml.callback');

// BML webhook (PRIMARY method for payment confirmation; no locale)
Route::post('webhooks/bml', \App\Http\Controllers\BmlWebhookController::class)
    ->name('webhooks.bml')->middleware('throttle:120,1');

// BML return URL and status poll - must be outside localized group so BML's
// redirect lands here directly without a locale-prefix 302 redirect dropping query params.
Route::get('payments/bml/return', [\App\Http\Controllers\PaymentController::class, 'return'])
    ->name('payments.bml.return');
Route::get('payments/status/{payment}', [\App\Http\Controllers\PaymentController::class, 'statusByPayment'])
    ->name('payments.status.by_id');

// Localized routes
Route::group([
    'prefix' => LaravelLocalization::setLocale(),
    'middleware' => ['localeSessionRedirect', 'localizationRedirect', 'localeViewPath']
], function() {
    // Public site routes
    require __DIR__.'/web_public.php';
    
    // Portal/dashboard routes
    require __DIR__.'/web_localized.php';
});
