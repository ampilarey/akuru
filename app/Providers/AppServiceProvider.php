<?php

namespace App\Providers;

use App\Services\Payment\PaymentProviderInterface;
use App\Services\Payment\PaymentService;
use App\Services\SmsGatewayService;
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
        //
    }
}
