<?php

namespace App\Console\Commands;

use App\Domains\Finance\Actions\SendInvoiceRemindersAction;
use Illuminate\Console\Command;

class SendInvoiceRemindersCommand extends Command
{
    protected $signature = 'invoices:send-reminders';

    protected $description = 'SMS financially-responsible guardians about overdue invoices (throttled)';

    public function handle(SendInvoiceRemindersAction $action): int
    {
        $count = $action->execute();
        $this->info("Sent {$count} invoice reminders.");

        return self::SUCCESS;
    }
}
