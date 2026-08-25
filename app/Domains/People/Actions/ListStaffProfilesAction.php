<?php

namespace App\Domains\People\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListStaffProfilesAction
{
    /**
     * @param  array{status?: string, department?: string}  $filters
     * @return Collection<int, object>
     */
    public function execute(array $filters = []): Collection
    {
        $query = DB::table('staff_profiles')
            ->select([
                'id',
                'user_id',
                'staff_number',
                'first_name',
                'last_name',
                'first_name_dhivehi',
                'last_name_dhivehi',
                'first_name_arabic',
                'last_name_arabic',
                'department',
                'designation',
                'employment_type',
                'status',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        return $query->get();
    }
}
