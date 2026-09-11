<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\MovementDirection;
use App\Domains\Academics\Models\StudentMovement;
use App\Domains\People\Actions\ListStudentsByIdsAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The gate console for one day: what has been recorded, newest first, and who
 * the school currently believes is inside.
 *
 * "Currently inside" is derived from the last live movement per child rather
 * than stored, so a void corrects it with no second write to keep in step.
 */
class ListGateMovementsAction
{
    /**
     * @return array{movements: Collection<int, array<string, mixed>>, in_count: int, out_count: int}
     */
    public function execute(?string $date = null): array
    {
        $date ??= now()->toDateString();

        $movements = StudentMovement::query()
            ->whereDate('at', $date)
            ->orderByDesc('at')
            ->orderByDesc('id')
            ->get();

        if ($movements->isEmpty()) {
            return ['movements' => collect(), 'in_count' => 0, 'out_count' => 0];
        }

        $students = app(ListStudentsByIdsAction::class)
            ->execute($movements->pluck('student_id')->all())
            ->keyBy('id');

        $staff = DB::table('users')
            ->whereIn('id', $movements->pluck('recorded_by')->filter()->all())
            ->pluck('name', 'id');

        $rows = $movements->map(fn (StudentMovement $movement): array => [
            'id' => (int) $movement->id,
            'student_id' => (int) $movement->student_id,
            'student' => $students->get((int) $movement->student_id)['name'] ?? 'Unknown',
            'student_number' => $students->get((int) $movement->student_id)['student_number'] ?? null,
            'direction' => $movement->direction->value,
            'direction_label' => $movement->direction->label(),
            'at' => $movement->at?->toDateTimeString(),
            'source' => $movement->source->value,
            'source_label' => $movement->source->label(),
            'recorded_by' => $movement->recorded_by ? ($staff->get((int) $movement->recorded_by) ?? 'Unknown') : null,
            'note' => $movement->note,
            'voided' => $movement->voided_at !== null,
        ]);

        // The last *live* movement per child is what the school believes now.
        $latest = $movements->whereNull('voided_at')->groupBy('student_id')
            ->map(fn (Collection $forStudent) => $forStudent->sortByDesc('at')->first());

        return [
            'movements' => $rows->values(),
            'in_count' => $latest->filter(fn ($m) => $m->direction === MovementDirection::In)->count(),
            'out_count' => $latest->filter(fn ($m) => $m->direction === MovementDirection::Out)->count(),
        ];
    }

    /**
     * Where the school believes each of these children is right now — used to
     * warn an operator before they record a second arrival in a row.
     *
     * @param  list<int>  $studentIds
     * @return array<int, string> student id => 'in'|'out'
     */
    public function currentStateFor(array $studentIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));

        if ($ids === []) {
            return [];
        }

        return StudentMovement::query()
            ->live()
            ->whereIn('student_id', $ids)
            ->orderBy('at')
            ->orderBy('id')
            ->get(['student_id', 'direction'])
            // Ordered ascending, so the last write per child wins.
            ->reduce(function (array $carry, StudentMovement $movement): array {
                $carry[(int) $movement->student_id] = $movement->direction->value;

                return $carry;
            }, []);
    }
}
