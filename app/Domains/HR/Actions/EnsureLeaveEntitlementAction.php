<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\LeaveEntitlement;
use App\Domains\HR\Models\LeaveType;

class EnsureLeaveEntitlementAction
{
    public function execute(int $staffProfileId, int $leaveTypeId, int $academicYearId, float $carriedOver = 0): LeaveEntitlement
    {
        $existing = LeaveEntitlement::query()
            ->where('staff_profile_id', $staffProfileId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('academic_year_id', $academicYearId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $type = LeaveType::query()->findOrFail($leaveTypeId);
        $entitled = (float) $type->days_per_year;

        $entitlement = LeaveEntitlement::query()->create([
            'staff_profile_id' => $staffProfileId,
            'leave_type_id' => $leaveTypeId,
            'academic_year_id' => $academicYearId,
            'entitled_days' => $entitled,
            'carried_over_days' => $carriedOver,
            'adjusted_days' => 0,
        ]);

        if ($entitled != 0.0) {
            app(AppendLeaveLedgerAction::class)->execute($entitlement->id, $entitled, 'entitled');
        }

        if ($carriedOver != 0.0) {
            app(AppendLeaveLedgerAction::class)->execute($entitlement->id, $carriedOver, 'carry_in');
        }

        return $entitlement;
    }
}
