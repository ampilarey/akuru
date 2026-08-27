<?php

namespace App\Console\Commands;

use App\Domains\Academics\Actions\NotifyUnfilledRegistersAction;
use Illuminate\Console\Command;

class NotifyUnfilledRegistersCommand extends Command
{
    protected $signature = 'registers:notify-unfilled';

    protected $description = 'Remind teachers once a day about registers past their lesson time that are still unfilled';

    public function handle(NotifyUnfilledRegistersAction $action): int
    {
        $count = $action->execute();
        $this->info("Sent {$count} unfilled-register reminder(s).");

        return self::SUCCESS;
    }
}
