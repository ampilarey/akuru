<?php

namespace App\Domains\Library\Providers;

use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Finance\Events\PaymentRefunded;
use App\Domains\Library\Listeners\GrantLibraryAccessOnPaymentConfirmed;
use App\Domains\Library\Listeners\RevokeLibraryAccessOnPaymentRefunded;
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
        // from money to a library access grant; a full refund revokes it.
        Event::listen(PaymentConfirmed::class, GrantLibraryAccessOnPaymentConfirmed::class);
        Event::listen(PaymentRefunded::class, RevokeLibraryAccessOnPaymentRefunded::class);
    }
}
