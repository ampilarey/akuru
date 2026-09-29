<?php

use App\Domains\Bookshop\Http\Controllers\CatalogueApiController;
use App\Domains\Notifications\Http\Controllers\NotificationController;
use App\Domains\Notifications\Http\Controllers\SmsApiController;
use App\Domains\Website\Http\Controllers\PublicSite\PrayerTimesController;
use App\Http\Controllers\Api\TestDeployWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('v1/prayer-times', [PrayerTimesController::class, 'json'])->middleware('throttle:60,1,api-prayer-times');
Route::get('v1/prayer-times/islands', [PrayerTimesController::class, 'islands'])->middleware('throttle:60,1,api-prayer-islands');

// STATUS §5ll: the Bookstore's public catalogue, read-only — what /shop shows, as JSON.
Route::prefix('v1/bookstore')->middleware('throttle:60,1,api-bookstore')->group(function () {
    Route::get('products', [CatalogueApiController::class, 'products'])->name('api.bookstore.products');
    Route::get('products/{slug}', [CatalogueApiController::class, 'product'])->name('api.bookstore.product');
    Route::get('shops', [CatalogueApiController::class, 'shops'])->name('api.bookstore.shops');
    Route::get('categories', [CatalogueApiController::class, 'categories'])->name('api.bookstore.categories');
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// TEST-only immediate deploy trigger (GitHub Actions -> cPanel self-pull). Disabled
// when TEST_DEPLOY_WEBHOOK_SECRET is unset; always 404 on non-test hosts.
Route::post('/deploy/test-pull', TestDeployWebhookController::class)
    ->middleware('throttle:10,1,deploy-test-pull');

// SMS API (akuru.edu.mv/api/v2) - API key auth
Route::prefix('v2')->group(function () {
    Route::get('health', [SmsApiController::class, 'health']);
    Route::post('sms/send', [SmsApiController::class, 'send']);
});

// Notification API routes
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/recent', [NotificationController::class, 'recent']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::get('/notifications/stats', [NotificationController::class, 'stats']);
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/send-test', [NotificationController::class, 'sendTest']);
});
