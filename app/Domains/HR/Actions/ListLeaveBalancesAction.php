<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\LeaveEntitlement;
use App\Domains\People\Actions\ListStaffProfilesAction;
use Illuminate\Support\Collection;

class ListLeaveBalancesAction
{
    /**
     * @param  array{academic_year_id?: int|null, staff_profile_id?: int|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $staff = app(ListStaffProfilesAction::class)->execute()->keyBy('id');

        $query = LeaveEntitlement::query()->with('leaveType')->orderBy('staff_profile_id');

        if (! empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', (int) $filters['academic_year_id']);
        }
        if (! empty($filters['staff_profile_id'])) {
            $query->where('staff_profile_id', (int) $filters['staff_profile_id']);
        }

        return $query->get()->map(function (LeaveEntitlement $row) use ($staff): array {
            $profile = $staff->get($row->staff_profile_id);

            return [
                'id' => $row->id,
                'staff_profile_id' => $row->staff_profile_id,
                'staff_name' => $profile ? trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')) : '',
                'leave_type_id' => $row->leave_type_id,
                'leave_type' => $row->leaveType?->name,
                'leave_code' => $row->leaveType?->code?->value ?? $row->leaveType?->code,
                'paid' => (bool) ($row->leaveType?->paid ?? true),
                'academic_year_id' => $row->academic_year_id,
                'entitled_days' => (float) $row->entitled_days,
                'carried_over_days' => (float) $row->carried_over_days,
                'adjusted_days' => (float) $row->adjusted_days,
                'balance' => app(LeaveBalanceCalculator::class)->execute($row->id),
            ];
        })->values();
    }
}
