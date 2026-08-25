<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Facades\DB;

class GuardianCanAccessStudentAction
{
    public function execute(int $guardianUserId, int $studentId): bool
    {
        $guardianIds = DB::table('parent_guardians')->where('user_id', $guardianUserId)->pluck('id');
        if ($guardianIds->isEmpty()) {
            return false;
        }

        return DB::table('guardian_student')
            ->whereIn('guardian_id', $guardianIds)
            ->where('student_id', $studentId)
            ->exists();
    }
}
