<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Facades\DB;

/**
 * Read-only counterpart to EnsureTeacherRowAction: which teacher row, if any,
 * belongs to this user. F4's teacher surfaces gate on it without creating rows.
 */
class ResolveTeacherForUserAction
{
    /**
     * @return array{id: int, name: string}|null
     */
    public function execute(int $userId): ?array
    {
        $row = DB::table('teachers')->where('user_id', $userId)->orderBy('id')->first();
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'name' => trim($row->first_name.' '.$row->last_name),
        ];
    }
}
