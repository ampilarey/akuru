<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Academics\Actions\ListAcademicYearsAction;
use App\Domains\Academics\Models\Term;
use App\Domains\Finance\Actions\GenerateInvoicesAction;
use App\Domains\Finance\Actions\IssueInvoicesAction;
use App\Domains\Finance\Actions\ListDraftInvoicesAction;
use App\Domains\Finance\Actions\ListFeeStructuresAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $years = app(ListAcademicYearsAction::class)->execute();
        $yearId = $request->integer('academic_year_id') ?: (int) ($years->firstWhere('is_current', true)['id'] ?? $years->first()['id'] ?? 0);
        $structureId = $request->integer('fee_structure_id') ?: null;
        $term = $yearId
            ? Term::query()
                ->where('academic_year_id', $yearId)
                ->orderByRaw("case when status = 'active' then 0 else 1 end")
                ->orderBy('sort_order')
                ->first()
            : null;

        return Inertia::render('Finance/Invoices/Index', [
            'years' => $years->values(),
            'yearId' => $yearId,
            'structures' => $yearId ? app(ListFeeStructuresAction::class)->execute($yearId)->values() : [],
            'structureId' => $structureId,
            'invoices' => app(ListDraftInvoicesAction::class)->execute($yearId ?: null, $structureId, false)->values(),
            'period_start' => $term?->start_date?->toDateString(),
            'period_end' => $term?->end_date?->toDateString(),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $data = $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'fee_structure_id' => ['required', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'term_id' => ['nullable', 'integer'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
            'monthly_mode' => ['nullable', 'string'],
            'include_optional' => ['sometimes', 'boolean'],
        ]);
        $data['created_by'] = $request->user()->id;

        $created = app(GenerateInvoicesAction::class)->execute($data);

        return redirect()
            ->route('finance.invoices.index', [
                'academic_year_id' => $data['academic_year_id'],
                'fee_structure_id' => $data['fee_structure_id'],
            ])
            ->with('success', $created->count().' draft invoices generated.');
    }

    public function issue(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $data = $request->validate([
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['integer'],
            'academic_year_id' => ['nullable', 'integer'],
            'fee_structure_id' => ['nullable', 'integer'],
        ]);

        $issued = app(IssueInvoicesAction::class)->execute($data['invoice_ids']);

        return redirect()
            ->route('finance.invoices.index', array_filter([
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'fee_structure_id' => $data['fee_structure_id'] ?? null,
            ]))
            ->with('success', $issued->count().' invoices issued.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $rows = app(ListDraftInvoicesAction::class)->execute(
            $request->integer('academic_year_id') ?: null,
            $request->integer('fee_structure_id') ?: null,
        );

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['number', 'student', 'period', 'due_date', 'total', 'status']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['invoice_number'],
                    $row['student_name'],
                    $row['period_key'],
                    $row['due_date'],
                    $row['total_amount'],
                    $row['status'],
                ]);
            }
            fclose($out);
        }, 'draft-invoices.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
