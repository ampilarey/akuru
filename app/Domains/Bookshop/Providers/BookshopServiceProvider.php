<?php

namespace App\Domains\Bookshop\Providers;

use App\Domains\Bookshop\Console\ExpireCheckoutsCommand;
use App\Domains\Bookshop\Listeners\MarkCheckoutPaidOnPaymentConfirmed;
use App\Domains\Finance\Events\PaymentConfirmed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class BookshopServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // B2, rule 12: a card checkout is paid on the webhook only.
        Event::listen(PaymentConfirmed::class, MarkCheckoutPaidOnPaymentConfirmed::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireCheckoutsCommand::class]);
        }
    }
}
