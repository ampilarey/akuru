<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Academics\Actions\ListAcademicYearsAction;
use App\Domains\Finance\Actions\ListFeeAdjustmentsAction;
use App\Domains\Finance\Actions\SaveFeeAdjustmentAction;
use App\Domains\Finance\Actions\SuggestSiblingFeeAdjustmentsAction;
use App\Domains\Finance\Enums\FeeAdjustmentAppliesTo;
use App\Domains\Finance\Enums\FeeAdjustmentBasis;
use App\Domains\Finance\Enums\FeeAdjustmentStatus;
use App\Domains\Finance\Enums\FeeAdjustmentType;
use App\Domains\Finance\Enums\FeeItemType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeeAdjustmentController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $years = app(ListAcademicYearsAction::class)->execute();
        $yearId = $request->integer('academic_year_id') ?: (int) ($years->firstWhere('is_current', true)['id'] ?? $years->first()['id'] ?? 0);
        $studentId = $request->integer('student_id') ?: null;

        return Inertia::render('Finance/Adjustments/Index', [
            'years' => $years->values(),
            'yearId' => $yearId,
            'studentId' => $studentId,
            'adjustments' => app(ListFeeAdjustmentsAction::class)->execute($yearId ?: null)->values(),
            'suggestions' => $studentId
                ? app(SuggestSiblingFeeAdjustmentsAction::class)->execute($studentId, $yearId)->values()
                : [],
            'types' => array_map(fn (FeeAdjustmentType $type) => $type->value, FeeAdjustmentType::cases()),
            'bases' => array_map(fn (FeeAdjustmentBasis $basis) => $basis->value, FeeAdjustmentBasis::cases()),
            'appliesTo' => array_map(fn (FeeAdjustmentAppliesTo $value) => $value->value, FeeAdjustmentAppliesTo::cases()),
            'statuses' => array_map(fn (FeeAdjustmentStatus $status) => $status->value, FeeAdjustmentStatus::cases()),
            'itemTypes' => array_map(fn (FeeItemType $type) => $type->value, FeeItemType::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
            'type' => ['required', 'string'],
            'basis' => ['required', 'string'],
            'value' => ['required', 'numeric', 'min:0.01'],
            'applies_to' => ['required', 'string'],
            'item_types' => ['nullable', 'array'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'string'],
        ]);
        if (($data['status'] ?? '') === FeeAdjustmentStatus::Approved->value) {
            $data['approved_by'] = $request->user()->id;
        }

        app(SaveFeeAdjustmentAction::class)->execute($data);

        return redirect()
            ->route('finance.adjustments.index', [
                'academic_year_id' => $data['academic_year_id'],
                'student_id' => $data['student_id'],
            ])
            ->with('success', 'Fee adjustment saved.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('finance.manage'), 403);

        $rows = app(ListFeeAdjustmentsAction::class)->execute($request->integer('academic_year_id') ?: null);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['student', 'type', 'basis', 'value', 'applies_to', 'status']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['student_name'],
                    $row['type'],
                    $row['basis'],
                    $row['value'],
                    $row['applies_to'],
                    $row['status'],
                ]);
            }
            fclose($out);
        }, 'fee-adjustments.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
