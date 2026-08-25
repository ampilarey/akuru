<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListStudentsByIdsAction
{
    /**
     * @param  list<int>  $studentIds
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $studentIds): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if ($ids === []) {
            return collect();
        }

        return DB::table('students')
            ->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name', 'student_id', 'class_id', 'status'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => trim($row->first_name.' '.$row->last_name),
                'student_number' => $row->student_id,
                'class_id' => $row->class_id ? (int) $row->class_id : null,
                'status' => $row->status,
            ]);
    }
}
