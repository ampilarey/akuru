<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\Payment\PaymentProviderInterface;
use App\Services\Payment\PaymentService;
use App\Services\SmsGatewayService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentProviderInterface::class, function ($app) {
            $driver = config('payments.providers.' . config('payments.default') . '.driver');

            return $app->make($driver);
        });

        $this->app->singleton(PaymentService::class, function ($app) {
            return new PaymentService(
                $app->make(PaymentProviderInterface::class),
                $app->make(SmsGatewayService::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share site settings globally with all views (cached for 10 min)
        View::composer('*', function ($view) {
            if (Schema::hasTable('settings')) {
                $siteSettings = Cache::remember('site_settings', 600, fn () => Setting::allKeyed());
                $view->with('siteSettings', $siteSettings);
            }
        });
    }
}
