<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Progress\Actions\ListPendingReviewsAction;
use Illuminate\Support\Collection;

class ListReviewQueueAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(): Collection
    {
        return app(ListPendingReviewsAction::class)->execute()
            ->map(function (array $row): array {
                if (($row['kind'] ?? '') === 'activity') {
                    $activity = Activity::query()->find($row['activity_id'] ?? 0);
                    $row['title'] = $activity?->title ?? 'Activity';
                    $row['prompt'] = $activity?->data['prompt'] ?? null;
                }
                if (($row['kind'] ?? '') === 'assessment') {
                    $assessment = Assessment::query()->find($row['assessment_id'] ?? 0);
                    $row['title'] = $assessment?->title ?? 'Assessment';
                }

                return $row;
            })
            ->values();
    }
}
