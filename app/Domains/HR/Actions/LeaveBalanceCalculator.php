<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\LeaveLedger;

class LeaveBalanceCalculator
{
    public function execute(int $entitlementId): float
    {
        return round((float) LeaveLedger::query()->where('entitlement_id', $entitlementId)->sum('days'), 1);
    }
}
