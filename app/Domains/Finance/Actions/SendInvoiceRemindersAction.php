<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Events\InvoiceReminderDue;
use App\Domains\Finance\Models\Invoice;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class SendInvoiceRemindersAction
{
    public function execute(?string $asOf = null): int
    {
        $settings = app(ResolveFinanceSettingsAction::class)->execute();
        $asOfDate = $asOf ?? now('Indian/Maldives')->toDateString();
        $cutoff = Carbon::parse($asOfDate, 'Indian/Maldives')->subDays($settings['reminder_days'])->toDateString();

        $invoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
            ->whereDate('due_date', '<=', $cutoff)
            ->whereColumn('paid_amount', '<', 'total_amount')
            ->get();

        $names = app(ListStudentsByIdsAction::class)
            ->execute($invoices->pluck('student_id')->all())
            ->keyBy('id');

        $sent = 0;
        foreach ($invoices as $invoice) {
            $key = 'invoice-reminder:'.$invoice->id;
            if (! Cache::add($key, true, now('Indian/Maldives')->addDays(7))) {
                continue;
            }

            $balance = number_format((float) $invoice->total_amount - (float) $invoice->paid_amount, 2, '.', '');
            event(new InvoiceReminderDue(
                $invoice->id,
                $invoice->student_id,
                $names[$invoice->student_id]['name'] ?? (string) $invoice->notes,
                $invoice->invoice_number,
                $balance,
                $invoice->due_date?->toDateString() ?? '',
            ));
            $sent++;
        }

        return $sent;
    }
}
