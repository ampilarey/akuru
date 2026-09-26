<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Commerce\Actions\ManageScopedDiscountCodesAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * The shop's own discount codes (BOOKSHOP_PLAN §6.5: "discount codes
 * funded by the vendor, scoped to their products — Commerce's discount
 * codes with a vendor scope"). The one discount system (rule 11): the code
 * lives in Commerce, scoped to `vendor` and this shop, funded by it — so
 * it takes only this shop's goods off at checkout and comes off its
 * earning, not Akuru's. Owners only: a code spends the shop's money.
 * A code never discounts delivery, and never a gift card (rule 12).
 */
class ManageVendorDiscountCodesAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(VendorScope $scope): array
    {
        return app(ManageScopedDiscountCodesAction::class)->list('vendor', $scope->vendorId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(VendorScope $scope, array $data, ?int $codeId = null): int
    {
        $this->owner($scope);
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if (! preg_match('/^[A-Z0-9][A-Z0-9-]{2,19}$/', $code)) {
            throw ValidationException::withMessages(['code' => __('shop.error_code_format')]);
        }
        $max = (float) config('bookshop.merchandising.vendor_code_max_percent', 90);
        if (($data['discount_type'] ?? '') === 'percentage' && (float) ($data['discount_value'] ?? 0) > $max) {
            throw ValidationException::withMessages(['discount_value' => __('shop.error_code_percent', ['max' => $max])]);
        }
        if (! empty($data['starts_at']) && ! empty($data['ends_at']) && $data['ends_at'] < $data['starts_at']) {
            throw ValidationException::withMessages(['ends_at' => __('shop.error_code_dates')]);
        }

        return app(ManageScopedDiscountCodesAction::class)->save('vendor', $scope->vendorId, [
            'code' => $code,
            'name' => trim((string) ($data['name'] ?? '')) ?: $scope->vendorName.' '.$code,
            'discount_type' => $data['discount_type'] ?? null,
            'discount_value' => $data['discount_value'] ?? null,
            'max_discount_amount' => ($data['max_discount_amount'] ?? null) ?: null,
            'minimum_order_amount' => ($data['minimum_order_amount'] ?? null) ?: null,
            'usage_limit' => ($data['usage_limit'] ?? null) ?: null,
            'per_user_limit' => ($data['per_user_limit'] ?? null) ?: 1,
            'starts_at' => ($data['starts_at'] ?? null) ?: null,
            'ends_at' => ! empty($data['ends_at']) ? $data['ends_at'].' 23:59:59' : null,
            'status' => 'active',
        ], $codeId, $scope->userId);
    }

    public function setStatus(VendorScope $scope, int $codeId, bool $active): void
    {
        $this->owner($scope);
        app(ManageScopedDiscountCodesAction::class)->setStatus('vendor', $scope->vendorId, $codeId, $active ? 'active' : 'inactive');
    }

    private function owner(VendorScope $scope): void
    {
        if (! $scope->isOwner()) {
            throw new AuthorizationException(__('shop.owner_only'));
        }
    }
}
