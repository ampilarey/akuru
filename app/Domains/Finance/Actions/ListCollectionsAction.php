<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\Invoice;
use Illuminate\Support\Collection;

class ListCollectionsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $yearId = null): Collection
    {
        $query = Invoice::query()->orderBy('issue_date');
        if ($yearId) {
            $query->where('academic_year_id', $yearId);
        }

        return $query->get()
            ->groupBy(fn (Invoice $invoice) => ($invoice->meta['class_id'] ?? 'none').'|'.($invoice->issue_date?->format('Y-m') ?? 'unknown'))
            ->map(function (Collection $rows, string $key) {
                [$classId, $month] = explode('|', $key, 2);

                return [
                    'class_id' => $classId === 'none' ? null : (int) $classId,
                    'month' => $month,
                    'billed' => number_format($rows->sum(fn (Invoice $row) => (float) $row->total_amount), 2, '.', ''),
                    'collected' => number_format($rows->sum(fn (Invoice $row) => (float) $row->paid_amount), 2, '.', ''),
                    'invoice_count' => $rows->count(),
                ];
            })
            ->values();
    }
}
