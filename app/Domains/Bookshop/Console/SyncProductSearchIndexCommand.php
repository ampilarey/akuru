<?php

namespace App\Domains\Bookshop\Console;

use App\Domains\Bookshop\Actions\Shop\SyncProductSearchIndexAction;
use Illuminate\Console\Command;

/** BOOKSHOP_PLAN B9e, hourly while the search driver is meilisearch: the catalogue to the search server. */
class SyncProductSearchIndexCommand extends Command
{
    protected $signature = 'bookshop:search-sync';

    protected $description = 'Send the bookstore catalogue to the search server (when BOOKSHOP_SEARCH_DRIVER=meilisearch)';

    public function handle(): int
    {
        $sent = app(SyncProductSearchIndexAction::class)->execute();
        $this->line($sent === null ? 'The search driver is the database; nothing to send.' : "Products sent to the search index: {$sent}");

        return self::SUCCESS;
    }
}
