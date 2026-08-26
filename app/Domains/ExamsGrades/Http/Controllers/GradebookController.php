<?php

namespace App\Domains\ExamsGrades\Http\Controllers;

use App\Domains\ExamsGrades\Actions\AuthorizeExamEntryAction;
use App\Domains\ExamsGrades\Actions\ComputeTermGradesAction;
use App\Domains\ExamsGrades\Actions\ListExamCatalogAction;
use App\Domains\ExamsGrades\Actions\ListGradebookAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GradebookController extends Controller
{
    public function index(Request $request): Response
    {
        $classId = $request->integer('class_id') ?: null;
        $subjectId = $request->integer('subject_id') ?: null;
        $termId = $request->integer('term_id') ?: null;

        if ($classId && $subjectId) {
            $this->authorizeClassSubject($request, $classId, $subjectId);
        } else {
            abort_unless($request->user()?->can('exams.manage') || $request->user()?->can('exams.enter-any'), 403);
        }

        $book = ($classId && $subjectId && $termId)
            ? app(ListGradebookAction::class)->execute($classId, $subjectId, $termId)
            : ['exams' => [], 'competencies' => [], 'rows' => []];

        return Inertia::render('ExamsGrades/Gradebook/Index', [
            ...app(ListExamCatalogAction::class)->execute(),
            ...$book,
            'classId' => $classId,
            'subjectId' => $subjectId,
            'termId' => $termId,
        ]);
    }

    public function compute(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'term_id' => ['required', 'integer'],
        ]);

        $this->authorizeClassSubject($request, (int) $data['class_id'], (int) $data['subject_id']);

        app(ComputeTermGradesAction::class)->execute(
            (int) $data['class_id'],
            (int) $data['subject_id'],
            (int) $data['term_id'],
        );

        return redirect()
            ->route('exams.gradebook.index', $data)
            ->with('success', 'Term grades recomputed.');
    }

    public function export(Request $request): StreamedResponse
    {
        $classId = $request->integer('class_id');
        $subjectId = $request->integer('subject_id');
        $termId = $request->integer('term_id');
        abort_unless($classId && $subjectId && $termId, 404);
        $this->authorizeClassSubject($request, $classId, $subjectId);

        $book = app(ListGradebookAction::class)->execute($classId, $subjectId, $termId);

        return response()->streamDownload(function () use ($book): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['student_id', 'name', 'percent', 'grade', 'point', 'rank']);
            foreach ($book['rows'] as $row) {
                fputcsv($out, [
                    $row['student_id'],
                    $row['name'],
                    $row['term']['weighted_percent'] ?? '',
                    $row['term']['grade'] ?? '',
                    $row['term']['grade_point'] ?? '',
                    $row['term']['rank'] ?? '',
                ]);
            }
            fclose($out);
        }, 'gradebook.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function redirectFromAcademics(Request $request): RedirectResponse
    {
        return redirect()->route('exams.gradebook.index', $request->query());
    }

    private function authorizeClassSubject(Request $request, int $classId, int $subjectId): void
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        abort_unless(app(AuthorizeExamEntryAction::class)->forClassSubject(
            $classId,
            $subjectId,
            (int) $user->id,
            (bool) $user->can('exams.enter-any'),
            (bool) $user->can('exams.manage'),
        ), 403);
    }
}
