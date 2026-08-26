<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListClassTeacherOptionsAction
{
    /**
     * Users who have a teachers row. `classes.class_teacher_id` stores users.id.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public function execute(): Collection
    {
        return DB::table('teachers')
            ->whereNotNull('user_id')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['user_id', 'first_name', 'last_name'])
            ->map(fn (object $row) => [
                'id' => (int) $row->user_id,
                'name' => trim($row->first_name.' '.$row->last_name),
            ])
            ->values();
    }
}
