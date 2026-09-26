<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Support\InsightsReport;

/**
 * The shop's funnel (BOOKSHOP_PLAN §6.8, slice B9e): its own counters
 * only, through the `VendorScope`.
 */
class VendorInsightsAction
{
    /**
     * @return array<string, mixed>
     */
    public function report(VendorScope $scope, int $days): array
    {
        return InsightsReport::build($scope->vendorId, $days);
    }
}
