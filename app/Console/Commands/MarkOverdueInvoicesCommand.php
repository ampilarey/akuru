<?php

namespace App\Console\Commands;

use App\Domains\Finance\Actions\MarkOverdueInvoicesAction;
use Illuminate\Console\Command;

class MarkOverdueInvoicesCommand extends Command
{
    protected $signature = 'invoices:mark-overdue';

    protected $description = 'Mark sent invoices overdue after the due date';

    public function handle(MarkOverdueInvoicesAction $action): int
    {
        $count = $action->execute();
        $this->info("Marked {$count} invoices overdue.");

        return self::SUCCESS;
    }
}
