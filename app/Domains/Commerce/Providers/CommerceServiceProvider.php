<?php

namespace App\Domains\Commerce\Providers;

use App\Domains\Commerce\Listeners\IssueGiftCardOnPaymentConfirmed;
use App\Domains\Finance\Events\PaymentConfirmed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // §15.3 / §43.5: a purchased gift card is issued on the webhook only.
        Event::listen(PaymentConfirmed::class, IssueGiftCardOnPaymentConfirmed::class);
    }
}
