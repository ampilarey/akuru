<?php

namespace App\Domains\Academics\Actions;

use Illuminate\Support\Facades\DB;

class ResolveTeacherIdForUserAction
{
    public function execute(?int $userId): ?int
    {
        if ($userId === null) {
            return null;
        }

        $id = DB::table('teachers')->where('user_id', $userId)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
