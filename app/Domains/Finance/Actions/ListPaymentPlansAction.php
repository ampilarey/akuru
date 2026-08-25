<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\PaymentPlan;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Illuminate\Support\Collection;

class ListPaymentPlansAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $yearId = null): Collection
    {
        $query = PaymentPlan::query()->with(['installments', 'invoice'])->orderByDesc('id');
        if ($yearId) {
            $query->whereHas('invoice', fn ($q) => $q->where('academic_year_id', $yearId));
        }

        $plans = $query->get();
        $names = app(ListStudentsByIdsAction::class)
            ->execute($plans->pluck('invoice.student_id')->filter()->all())
            ->keyBy('id');

        return $plans->map(function (PaymentPlan $plan) use ($names) {
            $paid = $plan->installments->sum(fn ($row) => (float) $row->paid_amount);

            return [
                'id' => $plan->id,
                'invoice_id' => $plan->invoice_id,
                'invoice_number' => $plan->invoice?->invoice_number,
                'student_id' => $plan->invoice?->student_id,
                'student_name' => $names[$plan->invoice?->student_id]['name'] ?? $plan->invoice?->notes,
                'total_amount' => $plan->total_amount,
                'paid_amount' => number_format($paid, 2, '.', ''),
                'status' => $plan->status?->value,
                'installments' => $plan->installments->map(fn ($row) => [
                    'id' => $row->id,
                    'sequence' => $row->sequence,
                    'due_date' => $row->due_date?->toDateString(),
                    'amount' => $row->amount,
                    'paid_amount' => $row->paid_amount,
                    'status' => $row->status?->value,
                ])->values(),
            ];
        });
    }
}
