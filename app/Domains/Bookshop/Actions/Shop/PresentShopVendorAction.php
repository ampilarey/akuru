<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Support\ShopPresenter;

/**
 * A vendor's page head (BOOKSHOP_PLAN §2 `/shop/<vendor>`): its name, its
 * tagline and "at Akuru Online Bookshop" (decision 11). B1b's page is the
 * plain one — the vendor's own branding, sections and pages arrive with the
 * storefront designer (B4, B5). Null for an unknown or suspended vendor.
 */
class PresentShopVendorAction
{
    /**
     * @return array{name: string, slug: string, tagline: ?string}|null
     */
    public function execute(string $slug): ?array
    {
        $vendor = Vendor::query()->where('slug', $slug)->where('status', VendorStatus::Active->value)->first();

        return $vendor === null ? null : ShopPresenter::vendor($vendor);
    }
}
