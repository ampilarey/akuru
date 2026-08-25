<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\StaffContractStatus;
use App\Domains\HR\Models\StaffContract;

class ResolveActiveStaffContractAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(int $staffProfileId): ?array
    {
        $row = StaffContract::query()
            ->where('staff_profile_id', $staffProfileId)
            ->where('status', StaffContractStatus::Active)
            ->orderByDesc('start_date')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => $row->id,
            'staff_profile_id' => $row->staff_profile_id,
            'contract_type' => $row->contract_type?->value ?? $row->contract_type,
            'start_date' => $row->start_date?->toDateString(),
            'end_date' => $row->end_date?->toDateString(),
            'basic_salary' => (float) $row->basic_salary,
            'allowances' => $row->allowances ?? [],
            'working_hours_per_week' => $row->working_hours_per_week,
            'status' => $row->status?->value ?? $row->status,
        ];
    }
}
