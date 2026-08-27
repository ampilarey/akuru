<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\Course;
use App\Domains\Progress\Actions\ListPendingReviewsAction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListReviewQueueAction
{
    /**
     * @param  array{academic_year_id?: int|null, course_id?: int|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $yearId = $this->positiveInt($filters['academic_year_id'] ?? null);
        $enrollmentIds = $yearId ? $this->enrollmentIdsForYear($yearId) : [];

        $pending = app(ListPendingReviewsAction::class)->execute($filters)
            ->filter(fn (array $row): bool => $this->matchesYear($row, $yearId, $enrollmentIds))
            ->values();

        $students = $this->students($pending->pluck('student_id')->all());
        $courses = $this->courses($pending->pluck('course_id')->all());
        $activities = Activity::query()
            ->whereIn('id', $pending->where('kind', 'activity')->pluck('activity_id')->filter()->all() ?: [0])
            ->get(['id', 'title', 'data'])
            ->keyBy('id');
        $assessments = Assessment::query()
            ->whereIn('id', $pending->where('kind', 'assessment')->pluck('assessment_id')->filter()->all() ?: [0])
            ->get(['id', 'title'])
            ->keyBy('id');

        return $pending->map(function (array $row) use ($students, $courses, $activities, $assessments): array {
            if (($row['kind'] ?? '') === 'activity') {
                $activity = $activities->get($row['activity_id'] ?? 0);
                $row['title'] = $activity?->title ?? 'Activity';
                $row['prompt'] = $activity?->data['prompt'] ?? null;
            }
            if (($row['kind'] ?? '') === 'assessment') {
                $assessment = $assessments->get($row['assessment_id'] ?? 0);
                $row['title'] = $assessment?->title ?? 'Assessment';
            }

            $student = $students->get($row['student_id'] ?? 0);
            $course = $courses->get($row['course_id'] ?? 0);
            $row['student_name'] = $student ? trim(($student->first_name ?? '').' '.($student->last_name ?? '')) : '';
            $row['course_title'] = $course ? (string) $course->title : '';
            $row['waiting_hours'] = $this->waitingHours($row['submitted_at'] ?? null);

            return $row;
        })->values();
    }

    /**
     * @param  list<mixed>  $ids
     * @return Collection<int|string, object>
     */
    public function students(array $ids): Collection
    {
        return DB::table('students')
            ->whereIn('id', array_values(array_filter(array_map('intval', $ids))) ?: [0])
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');
    }

    /**
     * @param  list<mixed>  $ids
     * @return Collection<int|string, Course>
     */
    public function courses(array $ids): Collection
    {
        return Course::query()
            ->whereIn('id', array_values(array_filter(array_map('intval', $ids))) ?: [0])
            ->get(['id', 'title'])
            ->keyBy('id');
    }

    /**
     * @return list<int>
     */
    public function enrollmentIdsForYear(int $yearId): array
    {
        return DB::table('course_enrollments')
            ->join('course_offerings', 'course_offerings.id', '=', 'course_enrollments.course_offering_id')
            ->where('course_offerings.academic_year_id', $yearId)
            ->whereNull('course_offerings.deleted_at')
            ->pluck('course_enrollments.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<int>  $enrollmentIds
     */
    public function matchesYear(array $row, ?int $yearId, array $enrollmentIds): bool
    {
        if ($yearId === null) {
            return true;
        }
        if (($row['academic_year_id'] ?? null) === $yearId) {
            return true;
        }
        $enrollmentId = (int) ($row['enrollment_id'] ?? 0);

        return $enrollmentId > 0 && in_array($enrollmentId, $enrollmentIds, true);
    }

    public function waitingHours(?string $submittedAt): ?int
    {
        if ($submittedAt === null || $submittedAt === '') {
            return null;
        }

        return (int) now()->diffInHours(Carbon::parse($submittedAt), true);
    }

    public function positiveInt(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
