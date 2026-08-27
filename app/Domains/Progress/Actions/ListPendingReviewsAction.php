<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Enums\ActivityAttemptStatus;
use App\Domains\Progress\Enums\AssessmentAttemptStatus;
use App\Domains\Progress\Models\ActivityAttempt;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ListPendingReviewsAction
{
    /**
     * @param  array{academic_year_id?: int|null, course_id?: int|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $courseId = $this->positiveInt($filters['course_id'] ?? null);

        $activities = ActivityAttempt::query()
            ->where('status', ActivityAttemptStatus::Submitted)
            ->when($courseId, fn (Builder $query) => $query->where('course_id', $courseId))
            ->orderBy('submitted_at')
            ->get()
            ->map(fn (ActivityAttempt $attempt): array => app(SaveActivityAttemptAction::class)->serialize($attempt) + [
                'kind' => 'activity',
            ]);

        $assessments = AssessmentAttempt::query()
            ->where('status', AssessmentAttemptStatus::Submitted)
            ->when($courseId, fn (Builder $query) => $query->where('course_id', $courseId))
            ->orderBy('submitted_at')
            ->get()
            ->map(fn (AssessmentAttempt $attempt): array => app(StartAssessmentAttemptAction::class)->serialize($attempt, includeKeys: true) + [
                'kind' => 'assessment',
            ]);

        return $activities->concat($assessments)->values();
    }

    private function positiveInt(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
