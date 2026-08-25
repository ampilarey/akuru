<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\InvoiceMonthlyMode;
use Illuminate\Support\Facades\DB;

class ResolveFinanceSettingsAction
{
    /**
     * @return array{monthly_mode: InvoiceMonthlyMode, reminder_days: int}
     */
    public function execute(): array
    {
        $rows = DB::table('settings')
            ->whereIn('key', ['finance.invoice_monthly_mode', 'finance.invoice_reminder_days'])
            ->pluck('value', 'key');

        $mode = InvoiceMonthlyMode::tryFrom((string) ($rows['finance.invoice_monthly_mode'] ?? 'per_month'))
            ?? InvoiceMonthlyMode::PerMonth;

        return [
            'monthly_mode' => $mode,
            'reminder_days' => max(0, (int) ($rows['finance.invoice_reminder_days'] ?? 3)),
        ];
    }
}
