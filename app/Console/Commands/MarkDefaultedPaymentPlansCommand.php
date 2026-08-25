<?php

namespace App\Console\Commands;

use App\Domains\Finance\Actions\MarkDefaultedPaymentPlansAction;
use Illuminate\Console\Command;

class MarkDefaultedPaymentPlansCommand extends Command
{
    protected $signature = 'invoices:mark-defaulted-plans';

    protected $description = 'Flag overdue installments and defaulted payment plans (no school lockout)';

    public function handle(MarkDefaultedPaymentPlansAction $action): int
    {
        $count = $action->execute();
        $this->info("Defaulted {$count} payment plans.");

        return self::SUCCESS;
    }
}
