<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Models\ActivityAttempt;
use Illuminate\Support\Collection;

class ListActivityAttemptsByIdsAction
{
    /**
     * One enrolment, several (a student's own report reads across every
     * enrolment they hold), or null for everyone's.
     *
     * @param  list<int>  $activityIds
     * @param  int|list<int>|null  $enrollmentId
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $activityIds, int|array|null $enrollmentId = null): Collection
    {
        $ids = array_values(array_filter(array_map('intval', $activityIds)));
        if ($ids === []) {
            return collect();
        }

        $query = ActivityAttempt::query()
            ->whereIn('activity_id', $ids)
            ->whereIn('status', ['submitted', 'scored'])
            ->orderByDesc('submitted_at');

        if (is_array($enrollmentId)) {
            $query->whereIn('enrollment_id', array_map('intval', $enrollmentId));
        } elseif ($enrollmentId !== null) {
            $query->where('enrollment_id', $enrollmentId);
        }

        return $query->get()->map(fn (ActivityAttempt $attempt): array => app(SaveActivityAttemptAction::class)->serialize($attempt));
    }
}
