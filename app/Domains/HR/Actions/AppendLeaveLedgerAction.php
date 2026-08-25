<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\LeaveLedger;

class AppendLeaveLedgerAction
{
    public function execute(int $entitlementId, float $days, string $reason, ?int $requestId = null): LeaveLedger
    {
        return LeaveLedger::query()->create([
            'entitlement_id' => $entitlementId,
            'request_id' => $requestId,
            'days' => $days,
            'reason' => $reason,
        ]);
    }
}
