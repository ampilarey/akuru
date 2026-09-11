<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The children a guardian may actually *collect* — not the children they may
 * see.
 *
 * Deliberately separate from `ListGuardianChildrenAction`, which is the general
 * "my children" list behind thirty-odd portal screens and must keep its shape.
 * Pick-up asks a narrower question, so it gets its own reader rather than a new
 * argument on the shared one.
 *
 * Offering a child here that `RequestPickupAction` would then refuse is not a
 * security hole — the gate still holds — but it is a parent at a school gate
 * being told "no" with no way to know why. The dropdown and the gate should
 * answer the same question, so both read `can_pickup`.
 *
 * @see GuardianMayCollectStudentAction the gate this list must agree with
 */
class ListCollectableChildrenAction
{
    /**
     * @return Collection<int, array{id: int, name: string, student_number: ?string}>
     */
    public function execute(int $guardianUserId): Collection
    {
        $guardianIds = DB::table('parent_guardians')->where('user_id', $guardianUserId)->pluck('id');

        if ($guardianIds->isEmpty()) {
            return collect();
        }

        return DB::table('guardian_student')
            ->join('students', 'students.id', '=', 'guardian_student.student_id')
            ->whereIn('guardian_student.guardian_id', $guardianIds)
            ->where('guardian_student.can_pickup', true)
            ->orderBy('students.first_name')
            ->get(['students.id', 'students.first_name', 'students.last_name', 'students.student_id'])
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => trim($row->first_name.' '.$row->last_name),
                'student_number' => $row->student_id,
            ])
            ->values();
    }
}
