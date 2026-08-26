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
            : ['exams' => [], 'grade_items' => [], 'competencies' => [], 'rows' => [], 'missing_weights' => false];

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
        $items = $book['grade_items'] ?? [];

        return response()->streamDownload(function () use ($book, $items): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(
                ['student_id', 'name'],
                array_map(fn (array $item): string => $item['label'], $items),
                ['percent', 'grade', 'point', 'rank'],
            ));
            foreach ($book['rows'] as $row) {
                $scores = [];
                foreach ($items as $item) {
                    $cell = $row['items'][$item['key']] ?? null;
                    $scores[] = $this->csvCell($cell);
                }
                fputcsv($out, array_merge(
                    [
                        $row['student_id'],
                        $row['name'],
                    ],
                    $scores,
                    [
                        $row['term']['weighted_percent'] ?? '',
                        $row['term']['grade'] ?? '',
                        $row['term']['grade_point'] ?? '',
                        $row['term']['rank'] ?? '',
                    ],
                ));
            }
            fclose($out);
        }, 'gradebook.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function redirectFromAcademics(Request $request): RedirectResponse
    {
        return redirect()->route('exams.gradebook.index', $request->query());
    }

    /**
     * @param  array{score?: float|null, status?: string|null, is_absent?: bool, is_exempt?: bool}|null  $cell
     */
    private function csvCell(?array $cell): string
    {
        if ($cell === null) {
            return '';
        }
        if ($cell['is_absent'] ?? false) {
            return 'Abs';
        }
        if ($cell['is_exempt'] ?? false) {
            return 'Ex';
        }
        if (($cell['status'] ?? null) === 'submitted') {
            return 'Pending';
        }
        if (! array_key_exists('score', $cell) || $cell['score'] === null) {
            return '';
        }

        return (string) $cell['score'];
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
