<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\BankStatementImport;
use App\Domains\Finance\Models\BankStatementLine;

class ListBankStatementLinesAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(?int $importId = null, ?string $status = null): array
    {
        $imports = BankStatementImport::query()
            ->withCount('lines')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (BankStatementImport $row): array => [
                'id' => $row->id,
                'original_filename' => $row->original_filename,
                'account_label' => $row->account_label,
                'period_start' => $row->period_start?->toDateString(),
                'period_end' => $row->period_end?->toDateString(),
                'line_count' => (int) $row->lines_count,
                'imported_at' => $row->created_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        $selected = $importId ?? ($imports[0]['id'] ?? null);

        $lines = $selected === null ? [] : BankStatementLine::query()
            ->where('bank_statement_import_id', $selected)
            ->when($status, fn ($q) => $q->where('match_status', $status))
            ->with('invoice:id,invoice_number,total_amount,paid_amount')
            ->orderBy('posted_on')
            ->orderBy('id')
            ->get()
            ->map(fn (BankStatementLine $line): array => [
                'id' => $line->id,
                'posted_on' => $line->posted_on?->toDateString(),
                'description' => $line->description,
                'reference' => $line->reference,
                'amount' => number_format((float) $line->amount, 2, '.', ''),
                'is_credit' => $line->isCredit(),
                'match_status' => $line->match_status?->value,
                'match_note' => $line->match_note,
                'invoice_number' => $line->invoice?->invoice_number,
                'matched_invoice_id' => $line->matched_invoice_id,
            ])
            ->values()
            ->all();

        return [
            'imports' => $imports,
            'selected_import_id' => $selected,
            'lines' => $lines,
            'status' => $status,
        ];
    }
}
