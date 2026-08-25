<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ListBehaviorRecordsAction;
use App\Domains\Academics\Actions\SaveBehaviorRecordAction;
use App\Domains\Academics\Enums\BehaviorType;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\BehaviorRecord;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BehaviorRecordController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('behavior.record') || $request->user()?->can('behavior.manage'), 403);

        $lister = app(ListBehaviorRecordsAction::class);
        $yearId = $request->integer('academic_year_id') ?: null;

        return Inertia::render('Academics/Behavior/Index', [
            'yearId' => $yearId,
            'years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name']),
            'types' => array_map(fn (BehaviorType $type) => $type->value, BehaviorType::cases()),
            'categories' => $lister->categories(),
            'records' => $lister->execute(['academic_year_id' => $yearId]),
            'canManage' => (bool) $request->user()?->can('behavior.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('behavior.record') || $request->user()?->can('behavior.manage'), 403);

        app(SaveBehaviorRecordAction::class)->execute(
            $this->validated($request) + ['recorded_by' => (int) $request->user()->id],
            null,
            (int) $request->user()->id,
        );

        return redirect()->route('academics.behavior.index', $request->only(['academic_year_id']))->with('success', 'Behavior record saved.');
    }

    public function update(Request $request, BehaviorRecord $behaviorRecord): RedirectResponse
    {
        abort_unless($request->user()?->can('behavior.manage'), 403);

        app(SaveBehaviorRecordAction::class)->execute($this->validated($request), $behaviorRecord, (int) $request->user()->id);

        return redirect()->route('academics.behavior.index', ['academic_year_id' => $behaviorRecord->academic_year_id])->with('success', 'Behavior record updated.');
    }

    public function destroy(Request $request, BehaviorRecord $behaviorRecord): RedirectResponse
    {
        abort_unless($request->user()?->can('behavior.manage'), 403);

        $yearId = $behaviorRecord->academic_year_id;
        app(SaveBehaviorRecordAction::class)->delete($behaviorRecord, (int) $request->user()->id);

        return redirect()->route('academics.behavior.index', ['academic_year_id' => $yearId])->with('success', 'Behavior record removed.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('behavior.record') || $request->user()?->can('behavior.manage'), 403);

        $rows = app(ListBehaviorRecordsAction::class)->execute([
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
        ]);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'date', 'student', 'type', 'category', 'points', 'parent_visible', 'description']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['id'],
                    $row['date'],
                    $row['student_name'],
                    $row['type'],
                    $row['category'],
                    $row['points'],
                    $row['parent_visible'] ? '1' : '0',
                    $row['description'],
                ]);
            }
            fclose($handle);
        }, 'behavior-records.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'type' => ['required', Rule::enum(BehaviorType::class)],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:5000'],
            'points' => ['nullable', 'integer'],
            'date' => ['required', 'date'],
            'parent_visible' => ['sometimes', 'boolean'],
            'requires_followup' => ['sometimes', 'boolean'],
            'followup_notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
