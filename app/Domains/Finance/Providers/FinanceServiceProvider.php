<?php

namespace App\Domains\Finance\Providers;

use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Services\Payment\PaymentService;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentProviderInterface::class, function ($app) {
            $driver = config('payments.providers.'.config('payments.default').'.driver');

            return $app->make($driver);
        });

        $this->app->singleton(PaymentService::class);
    }

    public function boot(): void
    {
        //
    }
}
