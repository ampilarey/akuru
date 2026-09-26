<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Support\ShopPresenter;

/**
 * A vendor's page head (BOOKSHOP_PLAN §2 `/shop/<vendor>`): its name, its
 * tagline and "at Akuru Bookstore" (decision 11), and — since B4 — its
 * published storefront (identity and theme), or the draft for the vendor's
 * own preview. Null for an unknown or suspended vendor. Without a
 * published storefront the page stays the plain B1b one.
 */
class PresentShopVendorAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(string $slug, bool $draft = false): ?array
    {
        $vendor = Vendor::query()->where('slug', $slug)->where('status', VendorStatus::Active->value)->with('storefront')->first();
        if ($vendor === null) {
            return null;
        }

        return ShopPresenter::vendor($vendor) + ['storefront' => app(ResolveStorefrontAction::class)->execute($vendor, $draft)];
    }
}
