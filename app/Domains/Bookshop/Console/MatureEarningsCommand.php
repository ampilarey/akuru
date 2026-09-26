<?php

namespace App\Domains\Bookshop\Console;

use App\Domains\Bookshop\Actions\Money\MatureVendorEarningsAction;
use Illuminate\Console\Command;

/** BOOKSHOP_PLAN B6, daily on the existing schedule: earnings past their return window become available. */
class MatureEarningsCommand extends Command
{
    protected $signature = 'bookshop:mature-earnings';

    protected $description = 'Make bookstore vendor earnings past their return window available for payout';

    public function handle(): int
    {
        $count = app(MatureVendorEarningsAction::class)->execute();
        $this->line("Earnings matured: {$count}");

        return self::SUCCESS;
    }
}
