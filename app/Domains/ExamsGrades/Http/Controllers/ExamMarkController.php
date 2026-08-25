<?php

namespace App\Domains\ExamsGrades\Http\Controllers;

use App\Domains\ExamsGrades\Actions\AuthorizeExamEntryAction;
use App\Domains\ExamsGrades\Actions\ImportExamMarksAction;
use App\Domains\ExamsGrades\Actions\ListExamMarksAction;
use App\Domains\ExamsGrades\Actions\SaveExamMarkAction;
use App\Domains\ExamsGrades\Models\Exam;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamMarkController extends Controller
{
    public function show(Request $request, Exam $exam): Response
    {
        $this->authorizeEntry($request, $exam);

        $grid = app(ListExamMarksAction::class)->execute($exam);

        return Inertia::render('ExamsGrades/Marks/Show', [
            'exam' => [
                'id' => $exam->id,
                'name' => $exam->name,
                'status' => $exam->status->value,
                'max_marks' => $exam->max_marks,
                'exam_date' => $exam->exam_date?->toDateString(),
            ],
            'rows' => $grid['rows']->values(),
            'progress' => $grid['progress'],
        ]);
    }

    public function update(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeEntry($request, $exam);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'marks' => ['nullable'],
            'is_absent' => ['sometimes', 'boolean'],
            'is_exempt' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        app(SaveExamMarkAction::class)->execute(
            $exam,
            (int) $data['student_id'],
            $data,
            (int) $request->user()->id,
        );

        return redirect()
            ->route('exams.marks.show', $exam)
            ->with('success', 'Mark saved.');
    }

    public function import(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorizeEntry($request, $exam);

        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.student_id' => ['required', 'integer'],
            'rows.*.marks' => ['nullable'],
            'rows.*.is_absent' => ['sometimes'],
            'rows.*.is_exempt' => ['sometimes'],
            'rows.*.remarks' => ['nullable', 'string'],
        ]);

        $result = app(ImportExamMarksAction::class)->execute($exam, $data['rows'], (int) $request->user()->id);

        return redirect()
            ->route('exams.marks.show', $exam)
            ->with('success', $result['saved'].' marks imported.');
    }

    public function export(Request $request, Exam $exam): StreamedResponse
    {
        $this->authorizeEntry($request, $exam);

        $grid = app(ListExamMarksAction::class)->execute($exam);

        return response()->streamDownload(function () use ($grid): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['student_id', 'student_number', 'name', 'marks', 'is_absent', 'is_exempt', 'remarks']);
            foreach ($grid['rows'] as $row) {
                fputcsv($out, [
                    $row['student_id'],
                    $row['student_number'],
                    $row['name'],
                    $row['marks'],
                    $row['is_absent'] ? '1' : '0',
                    $row['is_exempt'] ? '1' : '0',
                    $row['remarks'],
                ]);
            }
            fclose($out);
        }, 'exam-'.$exam->id.'-marks.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeEntry(Request $request, Exam $exam): void
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        abort_unless(app(AuthorizeExamEntryAction::class)->execute(
            $exam,
            (int) $user->id,
            (bool) $user->can('exams.enter-any'),
            (bool) $user->can('exams.manage'),
        ), 403);
    }
}
