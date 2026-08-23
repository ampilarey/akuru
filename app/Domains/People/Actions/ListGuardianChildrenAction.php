<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListGuardianChildrenAction
{
    /**
     * @return Collection<int, object>
     */
    public function executeForGuardianUserId(int $userId): Collection
    {
        $guardianIds = DB::table('parent_guardians')->where('user_id', $userId)->pluck('id');

        if ($guardianIds->isEmpty()) {
            return collect();
        }

        return DB::table('guardian_student')
            ->join('students', 'students.id', '=', 'guardian_student.student_id')
            ->whereIn('guardian_student.guardian_id', $guardianIds)
            ->select([
                'students.id',
                'students.student_id',
                'students.first_name',
                'students.last_name',
                'students.status',
                'guardian_student.relationship',
                'guardian_student.is_primary',
            ])
            ->orderBy('students.last_name')
            ->get();
    }
}
