<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListTeacherReviewReportsAction;
use App\Domains\Progress\Actions\ReviewAttemptAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogReviewController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render(
            'Courses/Catalog/Reviews',
            app(ListTeacherReviewReportsAction::class)->execute($this->filters($request)),
        );
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $payload = app(ListTeacherReviewReportsAction::class)->execute($this->filters($request));

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'section',
                'student',
                'course',
                'kind',
                'title',
                'score',
                'max_score',
                'percent',
                'passing_score',
                'attempt_count',
                'reason',
                'recommendation',
                'submitted_at',
                'waiting_hours',
            ]);
            foreach ($payload['rows'] as $row) {
                fputcsv($out, [
                    'pending_review',
                    $row['student_name'] ?? '',
                    $row['course_title'] ?? '',
                    $row['kind'] ?? '',
                    $row['title'] ?? '',
                    $row['score'] ?? '',
                    $row['max_score'] ?? '',
                    '',
                    '',
                    $row['attempt_number'] ?? '',
                    'Waiting for teacher score',
                    '',
                    $row['submitted_at'] ?? '',
                    $row['waiting_hours'] ?? '',
                ]);
            }
            foreach ($payload['weaknesses'] as $row) {
                fputcsv($out, [
                    'weakness',
                    $row['student_name'],
                    $row['course_title'],
                    $row['kind'],
                    $row['title'],
                    $row['score'],
                    $row['max_score'],
                    $row['percent'],
                    $row['passing_score'],
                    $row['attempt_count'],
                    $row['reason'],
                    $row['recommendation'],
                    $row['submitted_at'],
                    '',
                ]);
            }
            foreach ($payload['revisions'] as $row) {
                fputcsv($out, [
                    'revision',
                    $row['student_name'],
                    $row['course_title'],
                    $row['kind'],
                    $row['title'],
                    $row['score'],
                    $row['max_score'],
                    $row['percent'],
                    $row['passing_score'],
                    $row['attempt_count'],
                    $row['reason'],
                    $row['recommendation'],
                    $row['submitted_at'],
                    '',
                ]);
            }
            fclose($out);
        }, 'teacher-review-reports.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        app(ReviewAttemptAction::class)->execute(
            (string) $request->input('kind'),
            (int) $request->input('attempt_id'),
            $request->only(['score', 'max_score', 'feedback', 'item_scores']),
            (int) $request->user()->id,
        );

        return redirect()->route('catalog.reviews.index')->with('success', 'Review saved.');
    }

    /**
     * @return array{academic_year_id?: int|null, course_id?: int|null, threshold?: int|null}
     */
    private function filters(Request $request): array
    {
        return [
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
            'course_id' => $request->integer('course_id') ?: null,
            'threshold' => $request->integer('threshold') ?: null,
        ];
    }
}
