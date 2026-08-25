<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\LeaveEntitlement;
use Illuminate\Validation\ValidationException;

class AdjustLeaveBalanceAction
{
    public function execute(int $entitlementId, float $days, string $reason): LeaveEntitlement
    {
        if ($days == 0.0) {
            throw ValidationException::withMessages(['days' => 'Adjustment cannot be zero.']);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Adjustment reason is required.']);
        }

        $entitlement = LeaveEntitlement::query()->findOrFail($entitlementId);
        $entitlement->adjusted_days = round((float) $entitlement->adjusted_days + $days, 1);
        $entitlement->save();

        app(AppendLeaveLedgerAction::class)->execute($entitlement->id, $days, 'adjustment: '.$reason);

        return $entitlement->refresh();
    }
}
