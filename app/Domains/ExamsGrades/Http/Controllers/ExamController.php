<?php

namespace App\Domains\ExamsGrades\Http\Controllers;

use App\Domains\ExamsGrades\Actions\BulkScheduleExamsAction;
use App\Domains\ExamsGrades\Actions\ListExamCatalogAction;
use App\Domains\ExamsGrades\Actions\ListExamsAction;
use App\Domains\ExamsGrades\Actions\SaveExamAction;
use App\Domains\ExamsGrades\Actions\TransitionExamStatusAction;
use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Models\Exam;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $yearId = $request->integer('academic_year_id') ?: null;
        $exams = app(ListExamsAction::class)->execute($yearId);

        return Inertia::render('ExamsGrades/Exams/Index', [
            ...app(ListExamCatalogAction::class)->execute(),
            'exams' => $exams->values(),
            'ungraded' => app(ListExamsAction::class)->ungraded($exams),
            'statuses' => array_map(fn (ExamStatus $status) => $status->value, ExamStatus::cases()),
            'yearId' => $yearId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        app(SaveExamAction::class)->execute($this->validated($request), null, $request->user()?->id);

        return redirect()->route('exams.index')->with('success', 'Exam scheduled.');
    }

    public function update(Request $request, Exam $exam): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        app(SaveExamAction::class)->execute($this->validated($request), $exam, $request->user()?->id);

        return redirect()->route('exams.index')->with('success', 'Exam updated.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $created = app(BulkScheduleExamsAction::class)->execute($this->validated($request), $request->user()?->id);

        return redirect()->route('exams.index')->with('success', count($created).' exams scheduled.');
    }

    public function transition(Request $request, Exam $exam): RedirectResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(ExamStatus::class)],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        app(TransitionExamStatusAction::class)->execute(
            $exam,
            ExamStatus::from($data['status']),
            (int) $request->user()->id,
            $data['reason'] ?? null,
        );

        return redirect()->route('exams.index')->with('success', 'Exam status updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('exams.manage'), 403);

        $rows = app(ListExamsAction::class)->execute($request->integer('academic_year_id') ?: null);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'name', 'class_id', 'subject_id', 'exam_date', 'status', 'max_marks', 'room_id']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['name'],
                    $row['class_id'],
                    $row['subject_id'],
                    $row['exam_date'],
                    $row['status'],
                    $row['max_marks'],
                    $row['room_id'],
                ]);
            }
            fclose($out);
        }, 'exams.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'term_id' => ['required', 'integer'],
            'class_id' => ['required', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'subject_ids' => ['sometimes'],
            'exam_type_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'exam_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'room_id' => ['nullable', 'integer'],
            'max_marks' => ['nullable', 'numeric', 'min:1'],
            'weight_override' => ['nullable', 'integer', 'min:0', 'max:100'],
            'instructions' => ['nullable', 'string'],
            'confirm_calendar' => ['sometimes', 'boolean'],
            'confirm_same_day' => ['sometimes', 'boolean'],
            'confirm_room' => ['sometimes', 'boolean'],
        ]);
    }
}
