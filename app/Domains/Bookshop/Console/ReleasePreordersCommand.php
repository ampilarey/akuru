<?php

namespace App\Domains\Bookshop\Console;

use App\Domains\Bookshop\Actions\Orders\ReleasePreordersAction;
use Illuminate\Console\Command;

/** COMMERCE_PARITY_PLAN P8d, daily: tell the buyers and the shops of the pre-orders whose release date has come. */
class ReleasePreordersCommand extends Command
{
    protected $signature = 'bookshop:release-preorders';

    protected $description = 'Tell buyers and shops of the Bookstore pre-orders whose release date has come';

    public function handle(): int
    {
        $this->line('Pre-orders released: '.app(ReleasePreordersAction::class)->releaseDue());

        return self::SUCCESS;
    }
}
