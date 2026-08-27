<?php

namespace App\Domains\Website\Console;

use App\Domains\Website\Actions\PublishDueDailyContentsAction;
use Illuminate\Console\Command;

class PublishDueDailyContentsCommand extends Command
{
    protected $signature = 'daily-content:publish-due';

    protected $description = 'Publish approved scheduled daily content whose publish_date is today or earlier';

    public function handle(PublishDueDailyContentsAction $action): int
    {
        $count = $action->execute();
        $this->info("Published {$count} scheduled daily content item(s).");

        return self::SUCCESS;
    }
}
