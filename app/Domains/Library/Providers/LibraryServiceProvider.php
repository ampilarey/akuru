<?php

namespace App\Domains\Library\Providers;

use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Library\Listeners\GrantLibraryAccessOnPaymentConfirmed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // L3 (§43.5): the webhook-confirmed payment event is the only path
        // from money to a library access grant.
        Event::listen(PaymentConfirmed::class, GrantLibraryAccessOnPaymentConfirmed::class);
    }
}
