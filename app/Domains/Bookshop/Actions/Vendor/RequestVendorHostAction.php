<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Actions\Shop\ResolveShopHostAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\Vendor;
use Illuminate\Validation\ValidationException;

/**
 * A shop asks for its own domain (BOOKSHOP_PLAN §2, slice B9f): a plain
 * host name like `fitrahbooks.mv` or `www.fitrahbooks.mv` — no scheme, no
 * path, not an Akuru address, not another shop's. Asking (or changing it)
 * puts it back to "requested" until the office turns it on; clearing it
 * stops it at once. Owners only (the controller checks).
 */
class RequestVendorHostAction
{
    public const PATTERN = '/^(?=.{4,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}$/';

    public function request(VendorScope $scope, ?string $host): Vendor
    {
        $vendor = Vendor::query()->findOrFail($scope->vendorId);
        $host = self::normalise($host);
        $old = $vendor->custom_host;

        if ($host === null) {
            $vendor->update(['custom_host' => null, 'custom_host_status' => null, 'custom_host_requested_at' => null, 'custom_host_approved_at' => null]);
            ResolveShopHostAction::forget($old);

            return $vendor->refresh();
        }
        self::check($host, $vendor->id);
        if ($host === $old) {
            return $vendor;
        }
        $vendor->update(['custom_host' => $host, 'custom_host_status' => 'requested', 'custom_host_requested_at' => now(), 'custom_host_approved_at' => null]);
        ResolveShopHostAction::forget($old);
        app(NotifyBookshopUserAction::class)->office(__('shop.notice_host_requested_title'), __('shop.notice_host_requested_body', ['vendor' => $vendor->name, 'host' => $host]), '/admin/bookshop');

        return $vendor->refresh();
    }

    private static function normalise(?string $host): ?string
    {
        $host = strtolower(trim((string) $host));
        $host = (string) preg_replace('#^https?://#', '', $host);
        $host = rtrim(explode('/', $host)[0], '.');

        return $host === '' ? null : $host;
    }

    private static function check(string $host, int $vendorId): void
    {
        if (preg_match(self::PATTERN, $host) !== 1) {
            throw ValidationException::withMessages(['custom_host' => __('shop.error_host_format')]);
        }
        $ours = array_filter([
            strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST)),
            strtolower(trim((string) config('bookshop.hosts.shop_host'))),
            'akuru.edu.mv',
        ]);
        foreach ($ours as $reserved) {
            if ($host === $reserved || str_ends_with($host, '.'.$reserved)) {
                throw ValidationException::withMessages(['custom_host' => __('shop.error_host_ours')]);
            }
        }
        if (Vendor::query()->where('custom_host', $host)->where('id', '!=', $vendorId)->exists()) {
            throw ValidationException::withMessages(['custom_host' => __('shop.error_host_taken')]);
        }
    }
}
