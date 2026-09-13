<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\InvoiceMonthlyMode;
use App\Domains\Settings\Contracts\SettingsRepositoryInterface;

class ResolveFinanceSettingsAction
{
    public function __construct(private SettingsRepositoryInterface $settings) {}

    /**
     * @return array{monthly_mode: InvoiceMonthlyMode, reminder_days: int}
     */
    public function execute(): array
    {
        $rows = collect($this->settings->many(array_fill_keys([
            'finance.invoice_monthly_mode',
            'finance.invoice_reminder_days',
        ], null)));
        $mode = InvoiceMonthlyMode::tryFrom((string) ($rows['finance.invoice_monthly_mode'] ?? 'per_month'))
            ?? InvoiceMonthlyMode::PerMonth;

        return [
            'monthly_mode' => $mode,
            'reminder_days' => max(0, (int) ($rows['finance.invoice_reminder_days'] ?? 3)),
        ];
    }
}
