<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListTeachersByIdsAction
{
    /**
     * @param  list<int>  $teacherIds
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $teacherIds): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $teacherIds))));
        if ($ids === []) {
            return collect();
        }

        return DB::table('teachers')
            ->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => trim($row->first_name.' '.$row->last_name),
            ]);
    }
}
