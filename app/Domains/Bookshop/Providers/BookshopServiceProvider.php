<?php

namespace App\Domains\Bookshop\Providers;

use App\Domains\Bookshop\Console\ExpireCheckoutsCommand;
use App\Domains\Bookshop\Console\IssueCommissionInvoicesCommand;
use App\Domains\Bookshop\Console\MatureEarningsCommand;
use App\Domains\Bookshop\Console\RemindAbandonedCartsCommand;
use App\Domains\Bookshop\Console\SyncProductSearchIndexCommand;
use App\Domains\Bookshop\Contracts\ProductSearchInterface;
use App\Domains\Bookshop\Listeners\MarkCheckoutPaidOnPaymentConfirmed;
use App\Domains\Bookshop\Services\DatabaseProductSearch;
use App\Domains\Bookshop\Services\MeilisearchProductSearch;
use App\Domains\Finance\Events\PaymentConfirmed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class BookshopServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // B9e (§10 "Search"): the database search unless a search server is chosen.
        $this->app->bind(ProductSearchInterface::class, fn ($app) => config('bookshop.search.driver') === 'meilisearch'
            ? $app->make(MeilisearchProductSearch::class)
            : $app->make(DatabaseProductSearch::class));
    }

    public function boot(): void
    {
        // B2, rule 12: a card checkout is paid on the webhook only.
        Event::listen(PaymentConfirmed::class, MarkCheckoutPaidOnPaymentConfirmed::class);

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireCheckoutsCommand::class, MatureEarningsCommand::class, IssueCommissionInvoicesCommand::class, RemindAbandonedCartsCommand::class, SyncProductSearchIndexCommand::class]);
        }
    }
}
