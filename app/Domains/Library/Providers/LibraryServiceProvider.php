<?php

namespace App\Domains\Library\Providers;

use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Finance\Events\PaymentRefunded;
use App\Domains\Library\Console\SyncLibraryPagesCommand;
use App\Domains\Library\Contracts\PdfPageTextExtractor;
use App\Domains\Library\Listeners\GrantLibraryAccessOnPaymentConfirmed;
use App\Domains\Library\Listeners\RevokeLibraryAccessOnPaymentRefunded;
use App\Support\Pdf\PdfTextExtractor;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // §36: PDF originals become reader pages through this seam (rule 4).
        // The binding is the pure-PHP parser because the hosts this runs on
        // have no rasteriser (no Imagick, no poppler, no ghostscript).
        $this->app->bind(PdfPageTextExtractor::class, PdfTextExtractor::class);
    }

    public function boot(): void
    {
        // L3 (§43.5): the webhook-confirmed payment event is the only path
        // from money to a library access grant; a full refund revokes it.
        Event::listen(PaymentConfirmed::class, GrantLibraryAccessOnPaymentConfirmed::class);
        Event::listen(PaymentRefunded::class, RevokeLibraryAccessOnPaymentRefunded::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SyncLibraryPagesCommand::class]);
        }
    }
}
