<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Academics\Actions\ListAcademicYearsAction;
use App\Domains\Academics\Actions\ListClassesForYearAction;
use App\Domains\Finance\Actions\CopyFeeStructuresFromLastYearAction;
use App\Domains\Finance\Actions\ListFeeItemsAction;
use App\Domains\Finance\Actions\ListFeeStructuresAction;
use App\Domains\Finance\Actions\SaveFeeStructureAction;
use App\Domains\Finance\Enums\FeeFrequency;
use App\Domains\Finance\Enums\FeeStructureAppliesTo;
use App\Domains\Finance\Enums\FeeStructureStatus;
use App\Domains\Finance\Models\FeeStructure;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeeStructureController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $years = app(ListAcademicYearsAction::class)->execute();
        $yearId = $request->integer('academic_year_id') ?: (int) ($years->firstWhere('is_current', true)['id'] ?? $years->first()['id'] ?? 0);

        return Inertia::render('Finance/FeeStructures/Index', [
            'years' => $years->values(),
            'yearId' => $yearId,
            'classes' => $yearId ? app(ListClassesForYearAction::class)->execute($yearId)->values() : [],
            'feeItems' => app(ListFeeItemsAction::class)->execute()->values(),
            'structures' => $yearId ? app(ListFeeStructuresAction::class)->execute($yearId)->values() : [],
            'appliesTo' => array_map(fn (FeeStructureAppliesTo $value) => $value->value, FeeStructureAppliesTo::cases()),
            'statuses' => array_map(fn (FeeStructureStatus $value) => $value->value, FeeStructureStatus::cases()),
            'frequencies' => array_map(fn (FeeFrequency $value) => $value->value, FeeFrequency::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $structure = app(SaveFeeStructureAction::class)->execute($request->validate([
            'academic_year_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'applies_to' => ['required', 'string'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer'],
            'status' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.fee_item_id' => ['required', 'integer'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
            'items.*.frequency' => ['required', 'string'],
            'items.*.due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'items.*.is_mandatory' => ['sometimes', 'boolean'],
        ]));

        return redirect()
            ->route('finance.fee-structures.index', ['academic_year_id' => $structure->academic_year_id])
            ->with('success', 'Fee structure saved.');
    }

    public function update(Request $request, FeeStructure $feeStructure): RedirectResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $structure = app(SaveFeeStructureAction::class)->execute($request->validate([
            'academic_year_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'applies_to' => ['required', 'string'],
            'class_ids' => ['nullable', 'array'],
            'class_ids.*' => ['integer'],
            'status' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.fee_item_id' => ['required', 'integer'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
            'items.*.frequency' => ['required', 'string'],
            'items.*.due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'items.*.is_mandatory' => ['sometimes', 'boolean'],
        ]), $feeStructure);

        return redirect()
            ->route('finance.fee-structures.index', ['academic_year_id' => $structure->academic_year_id])
            ->with('success', 'Fee structure updated.');
    }

    public function copyLastYear(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $data = $request->validate([
            'academic_year_id' => ['required', 'integer'],
        ]);

        app(CopyFeeStructuresFromLastYearAction::class)->execute((int) $data['academic_year_id']);

        return redirect()
            ->route('finance.fee-structures.index', ['academic_year_id' => $data['academic_year_id']])
            ->with('success', 'Copied last year as drafts.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $yearId = $request->integer('academic_year_id');
        $rows = app(ListFeeStructuresAction::class)->execute($yearId ?: null);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'applies_to', 'class_ids', 'status', 'item_count']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['applies_to'],
                    implode('|', $row['class_ids'] ?? []),
                    $row['status'],
                    count($row['items'] ?? []),
                ]);
            }
            fclose($out);
        }, 'fee-structures.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
