<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Models\Invoice;
use App\Domains\People\Actions\ListFinanciallyResponsibleContactsAction;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ListArrearsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $yearId = null, ?string $asOf = null): Collection
    {
        $today = $asOf ?? now('Indian/Maldives')->toDateString();
        $query = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
            ->whereColumn('paid_amount', '<', 'total_amount')
            ->orderBy('due_date');
        if ($yearId) {
            $query->where('academic_year_id', $yearId);
        }

        $rows = $query->get();
        $students = app(ListStudentsByIdsAction::class)->execute($rows->pluck('student_id')->all())->keyBy('id');

        return $rows->map(function (Invoice $invoice) use ($today, $students) {
            $balance = round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2);
            $due = $invoice->due_date?->toDateString();
            $days = ($due && $due < $today)
                ? Carbon::parse($due, 'Indian/Maldives')->diffInDays(Carbon::parse($today, 'Indian/Maldives'))
                : 0;
            $bucket = $days >= 90 ? '90' : ($days >= 60 ? '60' : ($days >= 30 ? '30' : 'current'));
            $guardians = app(ListFinanciallyResponsibleContactsAction::class)->execute($invoice->student_id);

            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'student_id' => $invoice->student_id,
                'student_name' => $students[$invoice->student_id]['name'] ?? $invoice->notes,
                'class_id' => $invoice->meta['class_id'] ?? $students[$invoice->student_id]['class_id'] ?? null,
                'guardian_name' => $guardians->pluck('name')->filter()->implode(', '),
                'due_date' => $invoice->due_date?->toDateString(),
                'balance' => number_format($balance, 2, '.', ''),
                'days_overdue' => (int) $days,
                'aging_bucket' => $bucket,
                'status' => $invoice->status?->value,
            ];
        });
    }
}
