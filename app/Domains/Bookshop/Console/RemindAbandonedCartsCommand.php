<?php

namespace App\Domains\Bookshop\Console;

use App\Domains\Bookshop\Actions\Shop\RemindAbandonedCartsAction;
use Illuminate\Console\Command;

/** BOOKSHOP_PLAN B9c, hourly on the existing schedule: one reminder for a cart left for a day. */
class RemindAbandonedCartsCommand extends Command
{
    protected $signature = 'bookshop:remind-abandoned-carts';

    protected $description = 'Remind signed-in customers once about a bookstore cart left for a day';

    public function handle(): int
    {
        $count = app(RemindAbandonedCartsAction::class)->execute();
        $this->line("Cart reminders sent: {$count}");

        return self::SUCCESS;
    }
}
