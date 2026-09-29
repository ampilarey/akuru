<?php

namespace App\Domains\Library\Console;

use App\Domains\Library\Actions\RemindReviewersAction;
use Illuminate\Console\Command;

/**
 * RESEARCH_ARTICLES_PLAN R3b: peer-review reminders, once a day — three
 * days before a report is due, and on the day.
 */
class RemindReviewersCommand extends Command
{
    protected $signature = 'library:remind-reviewers';

    protected $description = 'Remind peer reviewers of reports due in three days or today (once each, in-app)';

    public function handle(RemindReviewersAction $remind): int
    {
        $this->info('Peer-review reminders sent: '.$remind->execute());

        return self::SUCCESS;
    }
}
