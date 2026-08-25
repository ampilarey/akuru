<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Models\ActivityAttempt;
use Illuminate\Support\Collection;

class ListActivityAttemptsByIdsAction
{
    /**
     * @param  list<int>  $activityIds
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $activityIds, ?int $enrollmentId = null): Collection
    {
        $ids = array_values(array_filter(array_map('intval', $activityIds)));
        if ($ids === []) {
            return collect();
        }

        $query = ActivityAttempt::query()
            ->whereIn('activity_id', $ids)
            ->whereIn('status', ['submitted', 'scored'])
            ->orderByDesc('submitted_at');

        if ($enrollmentId !== null) {
            $query->where('enrollment_id', $enrollmentId);
        }

        return $query->get()->map(fn (ActivityAttempt $attempt): array => app(SaveActivityAttemptAction::class)->serialize($attempt));
    }
}
