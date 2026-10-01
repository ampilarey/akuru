<?php

namespace App\Domains\Lending\Console;

use App\Domains\Lending\Actions\RemindLendingAction;
use Illuminate\Console\Command;

/** LENDING_AND_USED_BOOKS_PLAN L2: lending reminders, once a day — two days before, on the day, every day overdue. */
class RemindLendingCommand extends Command
{
    protected $signature = 'lending:remind';

    protected $description = 'Remind lenders and borrowers of books due in two days, due today, or overdue (once each; overdue once a day)';

    public function handle(RemindLendingAction $remind): int
    {
        $this->info('Lending reminders sent: '.$remind->execute());

        return self::SUCCESS;
    }
}
