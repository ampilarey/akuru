<?php

namespace App\Domains\Library\Console;

use App\Domains\Library\Actions\RemindReadersToContinueAction;
use Illuminate\Console\Command;

/**
 * B11 (LIBRARY_PLAN §41): the continue-reading reminder, once a day.
 */
class RemindReadersCommand extends Command
{
    protected $signature = 'library:remind-readers';

    protected $description = 'Nudge readers who left a book unfinished a week or more ago (once, in-app)';

    public function handle(RemindReadersToContinueAction $remind): int
    {
        $sent = $remind->execute();
        $this->info("Continue-reading reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
