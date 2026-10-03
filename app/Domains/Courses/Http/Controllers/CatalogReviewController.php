<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\ListCoursesTaughtByUserAction;
use App\Domains\Courses\Actions\ListTeacherReviewReportsAction;
use App\Domains\Progress\Actions\ReviewAttemptAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Teacher review (SPEC §36): the queue of work a machine cannot mark, the
 * weakness and revision reports, and the CSV.
 *
 * Two doors since C16 slice N6 (OWNER_ACTIONS 16, decided 2026-10-03:
 * "teachers mark only their own courses"). `courses.manage` — the dean,
 * the supervisor, a course creator — sees and marks the whole school's
 * work, as before. `courses.review` alone — the teacher — sees and marks
 * the courses assigned to them through their instructor profile
 * (`course_instructor`, `instructors.user_id`), and nothing else; a
 * teacher with no assignment sees an empty queue that says so.
 */
class CatalogReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeReviewer($request);

        return Inertia::render(
            'Courses/Catalog/Reviews',
            app(ListTeacherReviewReportsAction::class)->execute($this->filters($request)) + ['t' => Phrases::once('admin')],
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeReviewer($request);
        $payload = app(ListTeacherReviewReportsAction::class)->execute($this->filters($request));

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, [
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
                Csv::put($out, [
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
                Csv::put($out, [
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
                Csv::put($out, [
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
        $this->authorizeReviewer($request);
        app(ReviewAttemptAction::class)->execute(
            (string) $request->input('kind'),
            (int) $request->input('attempt_id'),
            $request->only(['score', 'max_score', 'feedback', 'item_scores']),
            (int) $request->user()->id,
            $this->ownCourseIds($request),
        );

        return redirect()->route('catalog.reviews.index')->with('success', 'Review saved.');
    }

    private function authorizeReviewer(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->can('courses.manage') || $user->can('courses.review')), 403);
    }

    /**
     * The courses this reviewer is narrowed to — `null` for the whole school.
     *
     * @return list<int>|null
     */
    private function ownCourseIds(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null || $user->can('courses.manage')) {
            return null;
        }

        return app(ListCoursesTaughtByUserAction::class)->execute((int) $user->id);
    }

    /**
     * @return array{academic_year_id?: int|null, course_id?: int|null, threshold?: int|null, course_ids?: list<int>|null}
     */
    private function filters(Request $request): array
    {
        $filters = [
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
            'course_id' => $request->integer('course_id') ?: null,
            'threshold' => $request->integer('threshold') ?: null,
        ];
        $own = $this->ownCourseIds($request);
        if ($own !== null) {
            $filters['course_ids'] = $own;
        }

        return $filters;
    }
}
