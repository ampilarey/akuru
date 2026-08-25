<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\StaffProfile;

class CreateStaffProfileAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $profile = StaffProfile::query()->create([
            'user_id' => (int) $data['user_id'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? '',
            'phone' => $data['phone'] ?? null,
            'department' => $data['department'] ?? null,
            'designation' => $data['designation'] ?? null,
            'employment_type' => $data['employment_type'] ?? 'full_time',
            'status' => $data['status'] ?? 'active',
        ]);

        return [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'first_name' => $profile->first_name,
            'last_name' => $profile->last_name,
        ];
    }
}
