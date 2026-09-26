<?php

namespace App\Domains\Bookshop\Console;

use App\Domains\Bookshop\Actions\Checkout\ExpireCheckoutsAction;
use Illuminate\Console\Command;

/**
 * BOOKSHOP_PLAN B2, on the existing schedule (routes/console.php) every ten
 * minutes: checkouts not paid in their window expire and release their
 * stock; bank-transfer checkouts with a slip waiting are kept.
 */
class ExpireCheckoutsCommand extends Command
{
    protected $signature = 'bookshop:expire-checkouts';

    protected $description = 'Expire unpaid bookstore checkouts and release the stock they hold';

    public function handle(): int
    {
        $result = app(ExpireCheckoutsAction::class)->execute();
        $this->line("Checkouts expired: {$result['expired']}; kept for a waiting slip: {$result['extended']}");

        return self::SUCCESS;
    }
}
