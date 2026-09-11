<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\StudentMovement;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Illuminate\Support\Collection;

/**
 * A family's view: my children's arrivals and departures.
 *
 * **Parent visibility is the point of E18**, not a bolt-on — a gate log the
 * family cannot see is an attendance register with extra steps.
 *
 * Voided rows are hidden here rather than shown struck through. Staff need to
 * see a correction was made; a parent needs to know where their child is, and
 * a list of retracted times answers a question they did not ask.
 */
class ListMovementsForGuardianAction
{
    /**
     * @param  list<int>  $studentIds  already scoped to this guardian by the caller
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $studentIds, ?string $date = null): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));

        if ($ids === []) {
            return collect();
        }

        $date ??= now()->toDateString();

        $movements = StudentMovement::query()
            ->live()
            ->whereIn('student_id', $ids)
            ->whereDate('at', $date)
            ->orderByDesc('at')
            ->get();

        if ($movements->isEmpty()) {
            return collect();
        }

        $students = app(ListStudentsByIdsAction::class)
            ->execute($movements->pluck('student_id')->all())
            ->keyBy('id');

        return $movements->map(fn (StudentMovement $movement): array => [
            'id' => (int) $movement->id,
            'student' => $students->get((int) $movement->student_id)['name'] ?? 'Unknown',
            'direction' => $movement->direction->value,
            'direction_label' => $movement->direction->label(),
            'at' => $movement->at?->toDateTimeString(),
            // Families are told a card recorded it rather than being left to
            // assume a member of staff watched their child walk out.
            'source_label' => $movement->source->label(),
        ])->values();
    }
}
