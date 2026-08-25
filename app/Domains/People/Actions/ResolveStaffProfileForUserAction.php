<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Facades\DB;

class ResolveStaffProfileForUserAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(int $userId): ?array
    {
        $row = DB::table('staff_profiles')->where('user_id', $userId)->first();

        if ($row === null) {
            return null;
        }

        return (array) $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function executeById(int $staffProfileId): ?array
    {
        $row = DB::table('staff_profiles')->where('id', $staffProfileId)->first();

        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function executeByStaffNumber(string $staffNumber): ?array
    {
        $row = DB::table('staff_profiles')->where('staff_number', $staffNumber)->first();

        return $row ? (array) $row : null;
    }
}
