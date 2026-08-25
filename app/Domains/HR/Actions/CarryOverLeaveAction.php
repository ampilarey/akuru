<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\LeaveEntitlement;
use Illuminate\Support\Facades\DB;

class CarryOverLeaveAction
{
    /**
     * @return list<array{staff_profile_id: int, leave_type_id: int, carried: float}>
     */
    public function execute(int $fromYearId, int $toYearId): array
    {
        $rows = LeaveEntitlement::query()
            ->with('leaveType')
            ->where('academic_year_id', $fromYearId)
            ->get();

        return DB::transaction(function () use ($rows, $toYearId): array {
            $report = [];

            foreach ($rows as $entitlement) {
                $remaining = max(0, app(LeaveBalanceCalculator::class)->execute($entitlement->id));
                $cap = (float) ($entitlement->leaveType?->carry_over_max ?? 0);
                $carry = min($remaining, $cap);

                if ($carry > 0) {
                    app(AppendLeaveLedgerAction::class)->execute(
                        $entitlement->id,
                        -1 * $carry,
                        'carry_out',
                    );
                }

                app(EnsureLeaveEntitlementAction::class)->execute(
                    (int) $entitlement->staff_profile_id,
                    (int) $entitlement->leave_type_id,
                    $toYearId,
                    $carry,
                );

                $report[] = [
                    'staff_profile_id' => (int) $entitlement->staff_profile_id,
                    'leave_type_id' => (int) $entitlement->leave_type_id,
                    'carried' => $carry,
                ];
            }

            return $report;
        });
    }
}
