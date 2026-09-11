<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\StudentWork;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Illuminate\Support\Collection;

/**
 * A family's view of their children's work.
 *
 * Hidden rows never appear, and the ids are supplied by the caller from who
 * the viewer is — nothing in the request chooses the scope.
 */
class ListStudentWorkForGuardianAction
{
    /**
     * @param  list<int>  $studentIds  already scoped to this guardian by the caller
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $studentIds, int $limit = 60): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));

        if ($ids === []) {
            return collect();
        }

        $work = StudentWork::query()
            ->visible()
            ->whereIn('student_id', $ids)
            ->orderByDesc('done_on')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($work->isEmpty()) {
            return collect();
        }

        $students = app(ListStudentsByIdsAction::class)
            ->execute($work->pluck('student_id')->all())
            ->keyBy('id');

        return $work->map(fn (StudentWork $row): array => [
            'id' => (int) $row->id,
            'student' => $students->get((int) $row->student_id)['name'] ?? 'Unknown',
            'title' => $row->title,
            'note' => $row->note,
            'done_on' => $row->done_on?->toDateString(),
        ])->values();
    }
}
