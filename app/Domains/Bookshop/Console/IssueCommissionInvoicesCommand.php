<?php

namespace App\Domains\Bookshop\Console;

use App\Domains\Bookshop\Actions\Money\IssueCommissionInvoicesAction;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * BOOKSHOP_PLAN B6, on the first of each month: Akuru's commission tax
 * invoice to every vendor for the month just ended. `--month=YYYY-MM`
 * issues (or re-checks) another month; a month already invoiced is left.
 */
class IssueCommissionInvoicesCommand extends Command
{
    protected $signature = 'bookshop:issue-commission-invoices {--month= : The month to invoice, YYYY-MM (default: last month)}';

    protected $description = 'Issue the monthly commission tax invoices to bookstore vendors';

    public function handle(): int
    {
        $option = (string) $this->option('month');
        $month = $option !== '' ? Carbon::createFromFormat('Y-m', $option)->startOfMonth() : now()->subMonthNoOverflow()->startOfMonth();
        $issued = app(IssueCommissionInvoicesAction::class)->execute($month);
        $this->line('Commission invoices issued for '.$month->format('Y-m').': '.count($issued));
        foreach ($issued as $invoice) {
            $this->line('  '.$invoice->number.'  '.$invoice->currency.' '.$invoice->total);
        }

        return self::SUCCESS;
    }
}
