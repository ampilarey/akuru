<?php

namespace App\Domains\Bookshop\Actions\Money;

use App\Domains\Bookshop\Enums\EarningStatus;
use App\Domains\Bookshop\Models\VendorEarning;

/**
 * Pending earnings whose return window has passed become available
 * (BOOKSHOP_PLAN §5 "earnings maturing after the return window"). Run
 * daily by `bookshop:mature-earnings` on the existing schedule (§10), and
 * lazily wherever a balance is read or paid out.
 */
class MatureVendorEarningsAction
{
    public function execute(?int $vendorId = null): int
    {
        return VendorEarning::query()
            ->where('status', EarningStatus::Pending->value)
            ->whereNotNull('available_at')
            ->where('available_at', '<=', now())
            ->when($vendorId !== null, fn ($q) => $q->where('vendor_id', $vendorId))
            ->update(['status' => EarningStatus::Available->value]);
    }
}
