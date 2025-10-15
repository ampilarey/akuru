<?php

use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

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
