<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\InvoiceStatus;
use App\Domains\Finance\Models\Invoice;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Illuminate\Support\Collection;

class ListDraftInvoicesAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $yearId = null, ?int $structureId = null, bool $draftsOnly = true): Collection
    {
        $query = Invoice::query()->with('lines')->orderBy('invoice_number');
        if ($draftsOnly) {
            $query->where('status', InvoiceStatus::Draft->value);
        }
        if ($yearId) {
            $query->where('academic_year_id', $yearId);
        }
        if ($structureId) {
            $query->where('meta->fee_structure_id', $structureId);
        }

        $rows = $query->get();
        $names = app(ListStudentsByIdsAction::class)->execute($rows->pluck('student_id')->all())->keyBy('id');

        return $rows->map(fn (Invoice $invoice) => [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'student_id' => $invoice->student_id,
            'student_name' => $names[$invoice->student_id]['name'] ?? $invoice->notes,
            'period_key' => $invoice->meta['period_key'] ?? null,
            'due_date' => $invoice->due_date?->toDateString(),
            'total_amount' => $invoice->total_amount,
            'line_count' => $invoice->lines->count(),
            'status' => $invoice->status?->value,
        ]);
    }
}
