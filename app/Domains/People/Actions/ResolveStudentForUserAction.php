<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Facades\DB;

class ResolveStudentForUserAction
{
    /**
     * @return array{id: int, first_name: string, middle_name: ?string, last_name: string}|null
     */
    public function execute(int $userId): ?array
    {
        $row = DB::table('students')->where('user_id', $userId)->orderBy('id')->first();
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'first_name' => (string) $row->first_name,
            'middle_name' => $row->middle_name,
            'last_name' => (string) $row->last_name,
        ];
    }
}
