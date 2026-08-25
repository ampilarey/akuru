<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListFinanciallyResponsibleContactsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $studentId): Collection
    {
        return DB::table('guardian_student')
            ->join('parent_guardians', 'parent_guardians.id', '=', 'guardian_student.guardian_id')
            ->where('guardian_student.student_id', $studentId)
            ->where('guardian_student.financial_responsible', true)
            ->select([
                'parent_guardians.id as guardian_id',
                'parent_guardians.user_id',
                'parent_guardians.phone',
                'parent_guardians.email',
                'parent_guardians.first_name',
                'parent_guardians.last_name',
            ])
            ->get()
            ->map(fn ($row) => [
                'guardian_id' => (int) $row->guardian_id,
                'user_id' => $row->user_id ? (int) $row->user_id : null,
                'phone' => trim((string) $row->phone) ?: null,
                'email' => $row->email,
                'name' => trim($row->first_name.' '.$row->last_name),
            ]);
    }
}
