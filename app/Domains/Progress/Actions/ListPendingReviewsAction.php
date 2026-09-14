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

        // `academic_year_id` was in this method's signature and in nothing
        // else: the filter was documented, accepted and silently dropped, so a
        // caller narrowing a review queue to one year got every year back.
        //
        // It could not have worked before now anyway —
        // `AuthorizeActivityAccessAction` wrote a hardcoded null, so no attempt
        // carried a year to filter on. Both halves are fixed together, because
        // fixing either alone leaves the other looking correct.
        $yearId = $this->positiveInt($filters['academic_year_id'] ?? null);

        $activities = ActivityAttempt::query()
            ->where('status', ActivityAttemptStatus::Submitted)
            ->when($courseId, fn (Builder $query) => $query->where('course_id', $courseId))
            ->when($yearId, fn (Builder $query) => $query->where('academic_year_id', $yearId))
            ->orderBy('submitted_at')
            ->get()
            ->map(fn (ActivityAttempt $attempt): array => app(SaveActivityAttemptAction::class)->serialize($attempt) + [
                'kind' => 'activity',
            ]);

        $assessments = AssessmentAttempt::query()
            ->where('status', AssessmentAttemptStatus::Submitted)
            ->when($courseId, fn (Builder $query) => $query->where('course_id', $courseId))
            ->when($yearId, fn (Builder $query) => $query->where('academic_year_id', $yearId))
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
