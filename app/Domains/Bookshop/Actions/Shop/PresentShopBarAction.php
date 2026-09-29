<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Actions\Cart\ResolveCartAction;
use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorCollection;

/**
 * STATUS §5lt, the Bookstore's own tab bar on a phone (the owner, 2026-09-29:
 * "this doesn't fit for bookstore … make the tabs suitable for a shop … and
 * same buttons in each vendor page"). Home · Categories · Deals · Account ·
 * Cart, as on iruali.
 *
 * On the store's pages the tabs are the store's; on a shop's own pages they
 * are that shop's: its home, its categories and collections, its deals. The
 * categories come with how many are for sale, so the sheet the Categories
 * tab opens never lists an empty one.
 */
class PresentShopBarAction
{
    /**
     * @param  array<string, mixed>|null  $vendor  the page's shop (ShopPresenter::vendor), or null on the store's pages
     * @return array<string, mixed>
     */
    public function execute(?array $vendor, ?int $userId, ?string $cartToken): array
    {
        $shop = null;
        if (is_array($vendor) && isset($vendor['slug'])) {
            $shop = Vendor::query()->where('slug', (string) $vendor['slug'])->whereIn('status', [VendorStatus::Active->value])->first();
        }

        $homeUrl = $shop ? route('public.shop.vendor', $shop->slug) : route('public.shop.index');

        return [
            'shop' => $shop ? ['slug' => $shop->slug, 'name' => (string) ($vendor['storefront']['name'] ?? $vendor['name'] ?? $shop->name)] : null,
            'home_url' => $homeUrl,
            'deals_url' => $shop ? route('public.shop.vendor', ['vendor' => $shop->slug, 'deals' => 1]) : route('public.shop.deals'),
            'categories' => $this->categories($shop),
            'collections' => $shop ? $this->collections($shop) : [],
            'cart_count' => app(ResolveCartAction::class)->count($userId, $cartToken),
        ];
    }

    /**
     * @return list<array{name: string, url: string, count: int}>
     */
    private function categories(?Vendor $shop): array
    {
        $listing = app(ListShopProductsAction::class);
        $counts = ($shop ? $listing->query(['vendor' => $shop->slug], storefront: true) : $listing->query())
            ->reorder()
            ->whereNotNull('product_category_id')
            ->selectRaw('product_category_id, count(*) as aggregate')
            ->groupBy('product_category_id')
            ->pluck('aggregate', 'product_category_id');
        if ($counts->isEmpty()) {
            return [];
        }

        $locale = app()->getLocale();

        return ProductCategory::query()
            ->where('is_active', true)
            ->whereIn('id', $counts->keys()->all())
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(fn (ProductCategory $c) => [
                'name' => ($locale === 'dv' && $c->name_dv) ? $c->name_dv : (($locale === 'ar' && $c->name_ar) ? $c->name_ar : $c->name),
                'url' => $shop ? route('public.shop.vendor', ['vendor' => $shop->slug, 'category' => $c->slug]) : route('public.shop.category', $c->slug),
                'count' => (int) $counts->get($c->id, 0),
            ])->values()->all();
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    private function collections(Vendor $shop): array
    {
        return VendorCollection::query()
            ->where('vendor_id', $shop->id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (VendorCollection $c) => ['name' => $c->localizedName(), 'url' => route('public.shop.vendor.collection', [$shop->slug, $c->slug])])
            ->values()->all();
    }
}
