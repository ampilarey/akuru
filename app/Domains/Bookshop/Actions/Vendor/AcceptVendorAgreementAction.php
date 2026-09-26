<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\VendorMember;

/**
 * BOOKSHOP_PLAN §4 (audit finding 14): a member accepts the Vendor
 * Agreement on first sign-in to the portal, and the acceptance is dated on
 * their membership. Accepting twice keeps the first date.
 */
class AcceptVendorAgreementAction
{
    public function execute(VendorScope $scope): void
    {
        VendorMember::query()
            ->where('vendor_id', $scope->vendorId)
            ->where('user_id', $scope->userId)
            ->whereNull('agreement_accepted_at')
            ->update(['agreement_accepted_at' => now()]);
    }
}
