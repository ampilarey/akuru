<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\Vendor;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Which of the shop's notices also go by email or SMS (BOOKSHOP_PLAN §5
 * "Notifications: which events email or SMS them", slice B8). Every notice
 * is in the app regardless; this adds a channel per event. Email goes to
 * each member; SMS to the shop's contact phone. The office can close a
 * channel for every shop, which the page shows.
 */
class SaveVendorNoticeSettingsAction
{
    /**
     * @return array{events: array<string, array{email: bool, sms: bool}>, office: array<string, bool>, phone: ?string}
     */
    public function get(VendorScope $scope): array
    {
        $vendor = Vendor::query()->findOrFail($scope->vendorId);

        return [
            'events' => NotifyBookshopUserAction::vendorSettings(is_array($vendor->notice_settings) ? $vendor->notice_settings : null),
            'office' => NotifyBookshopUserAction::officeSwitches(),
            'phone' => $vendor->contact_phone,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $events
     */
    public function save(VendorScope $scope, array $events): void
    {
        if (! $scope->isOwner()) {
            throw new AuthorizationException(__('shop.owner_only'));
        }
        $out = [];
        foreach (array_keys((array) config('bookshop.notices.vendor_defaults')) as $event) {
            $out[$event] = [
                'email' => (bool) ($events[$event]['email'] ?? false),
                'sms' => (bool) ($events[$event]['sms'] ?? false),
            ];
        }
        Vendor::query()->whereKey($scope->vendorId)->update(['notice_settings' => json_encode($out)]);
    }
}
