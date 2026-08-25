<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\People\Actions\ListGuardianChildrenAction;
use Illuminate\Support\Collection;

class ListPublishedAwardsForGuardianAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $guardianUserId, ?int $studentId = null): Collection
    {
        $children = app(ListGuardianChildrenAction::class)->executeForGuardianUserId($guardianUserId);
        $childIds = $children->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($studentId !== null && ! in_array($studentId, $childIds, true)) {
            return collect();
        }

        $ids = $studentId !== null ? [$studentId] : $childIds;
        if ($ids === []) {
            return collect();
        }

        return app(ListAwardsAction::class)->issued()->filter(
            fn (array $row) => in_array((int) $row['student_id'], $ids, true),
        )->values();
    }
}
