<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListStudentsSharingFinancialGuardianAction
{
    /**
     * Other students who share a financially-responsible guardian.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $studentId): Collection
    {
        $guardianIds = DB::table('guardian_student')
            ->where('student_id', $studentId)
            ->where('financial_responsible', true)
            ->pluck('guardian_id');

        if ($guardianIds->isEmpty()) {
            return collect();
        }

        return DB::table('guardian_student')
            ->join('students', 'students.id', '=', 'guardian_student.student_id')
            ->join('parent_guardians', 'parent_guardians.id', '=', 'guardian_student.guardian_id')
            ->whereIn('guardian_student.guardian_id', $guardianIds)
            ->where('guardian_student.financial_responsible', true)
            ->where('guardian_student.student_id', '!=', $studentId)
            ->select([
                'students.id as student_id',
                'students.first_name',
                'students.last_name',
                'parent_guardians.id as guardian_id',
                'parent_guardians.first_name as guardian_first_name',
                'parent_guardians.last_name as guardian_last_name',
            ])
            ->get()
            ->unique('student_id')
            ->map(fn ($row) => [
                'student_id' => (int) $row->student_id,
                'student_name' => trim($row->first_name.' '.$row->last_name),
                'guardian_id' => (int) $row->guardian_id,
                'guardian_name' => trim($row->guardian_first_name.' '.$row->guardian_last_name),
            ])
            ->values();
    }
}
