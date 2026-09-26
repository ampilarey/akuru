<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\Vendor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * The shop's own settings that B3 needs (BOOKSHOP_PLAN §5 "Settings"):
 *
 *  - **Returns**: the window in days — seven at least (decision 8), longer
 *    if the shop offers it — and its conditions in its own words;
 *  - **Holiday mode** (audit finding 18): a date range and a notice. The
 *    products stay visible marked "back on <date>", the cart refuses them,
 *    and the shop's page shows the notice.
 *
 * Owners only: these bind the shop.
 */
class SaveVendorShopSettingsAction
{
    /**
     * @return array<string, mixed>
     */
    public function get(VendorScope $scope): array
    {
        $vendor = Vendor::query()->findOrFail($scope->vendorId);

        return [
            'return_window_days' => $vendor->returnWindowDays(),
            'return_conditions' => $vendor->return_conditions,
            'holiday_from' => $vendor->holiday_from?->toDateString(),
            'holiday_until' => $vendor->holiday_until?->toDateString(),
            'holiday_notice' => $vendor->holiday_notice,
            'on_holiday' => $vendor->onHoliday(),
            'minimum_window' => (int) config('bookshop.returns.window_days', 7),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(VendorScope $scope, array $data): void
    {
        if (! $scope->isOwner()) {
            throw new AuthorizationException(__('shop.owner_only'));
        }
        $minimum = (int) config('bookshop.returns.window_days', 7);
        $window = (int) ($data['return_window_days'] ?? $minimum);
        if ($window < $minimum || $window > (int) config('bookshop.returns.max_window_days', 60)) {
            throw ValidationException::withMessages(['return_window_days' => __('shop.error_return_window_days', ['min' => $minimum])]);
        }

        $from = ($data['holiday_from'] ?? '') ?: null;
        $until = ($data['holiday_until'] ?? '') ?: null;
        if (($from === null) !== ($until === null) || ($from !== null && $until < $from)) {
            throw ValidationException::withMessages(['holiday_until' => __('shop.error_holiday_dates')]);
        }

        Vendor::query()->whereKey($scope->vendorId)->update([
            'return_window_days' => $window,
            'return_conditions' => trim((string) ($data['return_conditions'] ?? '')) ?: null,
            'holiday_from' => $from,
            'holiday_until' => $until,
            'holiday_notice' => $from === null ? null : (trim((string) ($data['holiday_notice'] ?? '')) ?: null),
        ]);
    }
}
