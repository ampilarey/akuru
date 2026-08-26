<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Enums\ActivityAttemptStatus;
use App\Domains\Progress\Enums\AssessmentAttemptStatus;
use App\Domains\Progress\Models\ActivityAttempt;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListScoredAttemptsAction
{
    /**
     * Latest scored attempt per student + item, with submitted/scored attempt counts.
     *
     * @param  array{academic_year_id?: int|null, course_id?: int|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $courseId = $this->positiveInt($filters['course_id'] ?? null);

        $activityCounts = ActivityAttempt::query()
            ->whereIn('status', [ActivityAttemptStatus::Submitted, ActivityAttemptStatus::Scored])
            ->when($courseId, fn (Builder $query) => $query->where('course_id', $courseId))
            ->selectRaw('student_id, activity_id, COUNT(*) as attempt_count')
            ->groupBy('student_id', 'activity_id')
            ->get()
            ->keyBy(fn ($row): string => $this->activityKey((int) $row->student_id, (int) $row->activity_id));

        $activities = ActivityAttempt::query()
            ->where('status', ActivityAttemptStatus::Scored)
            ->when($courseId, fn (Builder $query) => $query->where('course_id', $courseId))
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (ActivityAttempt $attempt): string => $this->activityKey((int) $attempt->student_id, (int) $attempt->activity_id))
            ->map(function (ActivityAttempt $attempt) use ($activityCounts): array {
                $key = $this->activityKey((int) $attempt->student_id, (int) $attempt->activity_id);
                $row = app(SaveActivityAttemptAction::class)->serialize($attempt);
                unset($row['answers']);

                return $row + [
                    'kind' => 'activity',
                    'attempt_count' => (int) ($activityCounts->get($key)?->attempt_count ?? 1),
                ];
            });

        $assessmentCounts = AssessmentAttempt::query()
            ->whereIn('status', [AssessmentAttemptStatus::Submitted, AssessmentAttemptStatus::Scored])
            ->when($courseId, fn (Builder $query) => $query->where('course_id', $courseId))
            ->selectRaw('student_id, assessment_id, COUNT(*) as attempt_count')
            ->groupBy('student_id', 'assessment_id')
            ->get()
            ->keyBy(fn ($row): string => $this->assessmentKey((int) $row->student_id, (int) $row->assessment_id));

        $assessments = AssessmentAttempt::query()
            ->where('status', AssessmentAttemptStatus::Scored)
            ->when($courseId, fn (Builder $query) => $query->where('course_id', $courseId))
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (AssessmentAttempt $attempt): string => $this->assessmentKey((int) $attempt->student_id, (int) $attempt->assessment_id))
            ->map(function (AssessmentAttempt $attempt) use ($assessmentCounts): array {
                $key = $this->assessmentKey((int) $attempt->student_id, (int) $attempt->assessment_id);
                $row = app(StartAssessmentAttemptAction::class)->serialize($attempt);
                unset($row['answers'], $row['snapshots'], $row['item_scores']);

                return $row + [
                    'kind' => 'assessment',
                    'attempt_count' => (int) ($assessmentCounts->get($key)?->attempt_count ?? 1),
                ];
            });

        return $activities->concat($assessments)->values();
    }

    private function activityKey(int $studentId, int $activityId): string
    {
        return $studentId.':activity:'.$activityId;
    }

    private function assessmentKey(int $studentId, int $assessmentId): string
    {
        return $studentId.':assessment:'.$assessmentId;
    }

    private function positiveInt(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
