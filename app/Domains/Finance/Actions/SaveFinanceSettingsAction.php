<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\InvoiceMonthlyMode;
use App\Domains\Settings\Actions\SetSettingAction;
use Illuminate\Validation\ValidationException;

/**
 * The school's billing policy, set by the school.
 *
 * Three settings had been seeded by the S4.3 and S4.4 migrations, read by
 * `ResolveFinanceSettingsAction` and `MarkDefaultedPaymentPlansAction` on
 * every generation, reminder run and defaulting run — and were editable
 * from no screen. Changing how many days after the due date a family is
 * reminded meant a DBA and the `settings` table (S4 audit D3, STATUS §5fb).
 * The same shape as the attendance policy (E10d): every value validated
 * here, because each one changes what families are sent.
 */
class SaveFinanceSettingsAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): void
    {
        $mode = InvoiceMonthlyMode::tryFrom((string) ($data['invoice_monthly_mode'] ?? ''));
        if ($mode === null) {
            throw ValidationException::withMessages([
                'invoice_monthly_mode' => 'Choose one invoice per month or one consolidated invoice.',
            ]);
        }

        $reminderDays = (int) ($data['invoice_reminder_days'] ?? -1);
        if ($reminderDays < 0 || $reminderDays > 90) {
            throw ValidationException::withMessages([
                'invoice_reminder_days' => 'Between 0 (the day it falls due) and 90 days.',
            ]);
        }

        $defaultDays = (int) ($data['plan_default_days'] ?? -1);
        if ($defaultDays < 0 || $defaultDays > 365) {
            throw ValidationException::withMessages([
                'plan_default_days' => 'Between 0 (the day an installment falls overdue) and 365 days.',
            ]);
        }

        $set = app(SetSettingAction::class);
        $set->execute('finance.invoice_monthly_mode', $mode->value, 'string', 'finance', 'Monthly invoice mode (per_month or consolidated)');
        $set->execute('finance.invoice_reminder_days', $reminderDays, 'string', 'finance', 'Days after due date before a reminder SMS');
        $set->execute('finance.plan_default_days', $defaultDays, 'string', 'finance', 'Days an installment may be overdue before the plan is defaulted');
    }
}
