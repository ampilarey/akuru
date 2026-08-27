<?php

namespace App\Domains\Website\Console;

use App\Domains\Website\Actions\DeliverDailyContentSubscriptionsAction;
use Illuminate\Console\Command;

class DeliverDailyContentSubscriptionsCommand extends Command
{
    protected $signature = 'daily-content:deliver';

    protected $description = 'Send due daily-content SMS and email subscriptions for today';

    public function handle(DeliverDailyContentSubscriptionsAction $action): int
    {
        $count = $action->execute();
        $this->info("Delivered {$count} daily content subscription(s).");

        return self::SUCCESS;
    }
}
