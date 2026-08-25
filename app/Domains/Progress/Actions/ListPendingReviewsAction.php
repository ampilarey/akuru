<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Enums\ActivityAttemptStatus;
use App\Domains\Progress\Enums\AssessmentAttemptStatus;
use App\Domains\Progress\Models\ActivityAttempt;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Support\Collection;

class ListPendingReviewsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(): Collection
    {
        $activities = ActivityAttempt::query()
            ->where('status', ActivityAttemptStatus::Submitted)
            ->orderBy('submitted_at')
            ->get()
            ->map(fn (ActivityAttempt $attempt): array => app(SaveActivityAttemptAction::class)->serialize($attempt) + [
                'kind' => 'activity',
            ]);

        $assessments = AssessmentAttempt::query()
            ->where('status', AssessmentAttemptStatus::Submitted)
            ->orderBy('submitted_at')
            ->get()
            ->map(fn (AssessmentAttempt $attempt): array => app(StartAssessmentAttemptAction::class)->serialize($attempt, includeKeys: true) + [
                'kind' => 'assessment',
            ]);

        return $activities->concat($assessments)->values();
    }
}
