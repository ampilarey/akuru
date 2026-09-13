<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\People\Actions\CountStudentsAction;
use App\Domains\Progress\Actions\ListPendingReviewsAction;
use App\Domains\Progress\Actions\ListScoredAttemptsAction;
use Illuminate\Support\Collection;

/**
 * SPEC §33 "Admin Dashboard → Reports" names ten:
 *
 *   > Total students · Active enrollments · Course completion · Offering
 *   > completion · Lesson completion · Attendance reports · Assessment scores ·
 *   > Pending reviews · Certificates issued · Payment reports later
 *
 * Six of them were already computed, correctly, and **scattered across three
 * unrelated screens with no way to see them together**:
 *
 * | §33 report | Where it lived |
 * |---|---|
 * | Course completion | `/catalog/reports/completions` |
 * | Offering completion | same screen, second summary |
 * | Lesson completion | same screen, per-student columns |
 * | Attendance | same screen, `attendance_percent` |
 * | Pending reviews | `/catalog/reviews` |
 * | Certificates issued | `/catalog/certificates` |
 *
 * Three had no reader at all — **total students**, **active enrollments** and
 * **assessment scores** — though every one of them is a count or a call away:
 * `CountStudentsAction` existed and was used by the academics side,
 * `ListScoredAttemptsAction` already fed the teacher review report, and active
 * enrollments is a `where` clause.
 *
 * This composes rather than computes. Nothing here is a new figure; the point
 * is a single place that answers §33's question, which is the one thing an
 * administrator actually asked for. "Payment reports" is §33's own deferral
 * ("later") and is the one item left out.
 *
 * Rule 3: every cross-domain read goes through that domain's Action —
 * `CountStudentsAction` for People, `ListScoredAttemptsAction` and
 * `ListPendingReviewsAction` for Progress. Only `CourseEnrollment`, which
 * Courses owns, is read as a model.
 */
class ComposeCatalogReportsAction
{
    /**
     * @param  array{academic_year_id?: int|null, course_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function execute(array $filters = []): array
    {
        $completions = app(ListOfferingCompletionReportAction::class)->execute($filters);
        $courseId = $this->positiveInt($filters['course_id'] ?? null);

        return [
            'filters' => $completions['filters'] ?? [],
            'courses' => $completions['courses'] ?? [],
            'offerings' => $completions['offerings'] ?? [],
            'totals' => $this->totals($completions, $courseId),
            // §33's "Course completion" and "Offering completion" — already
            // computed, and now shown next to the totals they explain.
            'byCourse' => $completions['course_summaries'] ?? [],
            'byOffering' => $completions['offering_summaries'] ?? [],
            'scores' => $this->scores($filters),
            'pendingReviews' => $this->pendingReviews($filters),
            'certificates' => $this->certificates(),
        ];
    }

    /**
     * @param  array<string, mixed>  $completions
     * @return array<string, mixed>
     */
    private function totals(array $completions, ?int $courseId): array
    {
        $rows = collect($completions['rows'] ?? []);

        return [
            // §33 "Total students". Unfiltered on purpose: this is the roll of
            // the institute, not of a course, and filtering it by course would
            // make it a different number wearing the same label.
            'students' => app(CountStudentsAction::class)->execute(),

            // §33 "Active enrollments". §23's vocabulary: `suspended`,
            // `cancelled` and `rejected` are not active, and a `completed`
            // enrolment is finished rather than running.
            'active_enrollments' => CourseEnrollment::query()
                ->whereIn('status', ['active', 'approved'])
                ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
                ->count(),

            'enrolled_in_view' => $rows->count(),
            'completed_in_view' => $rows->where('status', 'completed')->count(),

            // §33 "Lesson completion", as one figure rather than a column to
            // add up by eye.
            'lessons_completed' => (int) $rows->sum(fn (array $row): int => (int) ($row['lessons_completed'] ?? 0)),
            'lessons_required' => (int) $rows->sum(fn (array $row): int => (int) ($row['lessons_required'] ?? 0)),

            // §33 "Attendance reports". Null rather than zero where no offering
            // in view schedules sessions — §24's "where applicable", and 0%
            // would read as "nobody turned up".
            'average_attendance' => $this->averageAttendance($rows),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function averageAttendance(Collection $rows): ?int
    {
        $values = $rows
            ->pluck('attendance_percent')
            ->filter(fn ($value): bool => $value !== null);

        return $values->isEmpty() ? null : (int) round($values->avg());
    }

    /**
     * §33 "Assessment scores". `ListScoredAttemptsAction` has been feeding the
     * teacher review report all along; nothing summarised it for an admin.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function scores(array $filters): array
    {
        $attempts = app(ListScoredAttemptsAction::class)->execute($filters);

        $percentages = $attempts
            ->filter(fn (array $row): bool => ($row['max_score'] ?? 0) > 0 && $row['score'] !== null)
            ->map(fn (array $row): float => ((float) $row['score'] / (float) $row['max_score']) * 100);

        return [
            'count' => $attempts->count(),
            'average_percent' => $percentages->isEmpty() ? null : (int) round($percentages->avg()),
            'recent' => $attempts->take(10)->map(fn (array $row): array => [
                'kind' => $row['kind'] ?? null,
                'student_id' => $row['student_id'] ?? null,
                'score' => $row['score'] ?? null,
                'max_score' => $row['max_score'] ?? null,
                'submitted_at' => $row['submitted_at'] ?? null,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function pendingReviews(array $filters): array
    {
        $pending = app(ListPendingReviewsAction::class)->execute($filters);

        return [
            'count' => $pending->count(),
            'oldest' => $pending->first()['submitted_at'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function certificates(): array
    {
        $issued = app(ListIssuedCertificatesAction::class)->execute();

        return [
            'issued' => $issued->reject(fn (array $row): bool => (bool) ($row['revoked'] ?? false))->count(),
            'revoked' => $issued->filter(fn (array $row): bool => (bool) ($row['revoked'] ?? false))->count(),
        ];
    }

    private function positiveInt(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
