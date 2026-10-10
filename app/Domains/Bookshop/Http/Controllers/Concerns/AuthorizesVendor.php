<?php

namespace App\Domains\Bookshop\Http\Controllers\Concerns;

use App\Domains\Bookshop\Actions\ResolveVendorScopeAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use Illuminate\Http\Request;

/**
 * The portal's gate, in one place: the signed-in person's membership of an
 * active vendor (the one chosen in the switcher, else their first), or 403.
 * Writes also need the Vendor Agreement accepted.
 */
trait AuthorizesVendor
{
    public const SESSION_VENDOR = 'bookshop.vendor_id';

    protected function authorizeVendor(Request $request, bool $needsAgreement = true): VendorScope
    {
        $scope = app(ResolveVendorScopeAction::class)->execute(
            (int) $request->user()->id,
            $request->session()->get(self::SESSION_VENDOR),
        );

        abort_if($scope === null, 403, __('shop.error_not_a_member'));
        abort_if($needsAgreement && ! $scope->agreementAccepted, 403, __('shop.error_agreement_first'));

        return $scope;
    }
}
