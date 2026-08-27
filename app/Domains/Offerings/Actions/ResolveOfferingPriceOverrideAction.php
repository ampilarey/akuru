<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Models\CourseOffering;

/**
 * P4.4 (SPEC §49 "Offerings may override course price"): the one place a
 * caller asks what an offering charges instead of its course. Null means
 * no override; 0 means the offering is free.
 */
class ResolveOfferingPriceOverrideAction
{
    public function execute(int $offeringId): ?float
    {
        $value = CourseOffering::query()->whereKey($offeringId)->value('price_override');

        return $value !== null ? (float) $value : null;
    }
}
