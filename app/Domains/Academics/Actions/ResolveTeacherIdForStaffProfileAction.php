<?php

namespace App\Domains\Academics\Actions;

use Illuminate\Support\Facades\DB;

class ResolveTeacherIdForStaffProfileAction
{
    public function execute(?int $staffProfileId): ?int
    {
        if ($staffProfileId === null) {
            return null;
        }

        $id = DB::table('teachers')->where('staff_profile_id', $staffProfileId)->value('id');

        return $id !== null ? (int) $id : null;
    }
}
