<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Enums\ProductVisibility;
use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Support\ShopPresenter;

/**
 * The bookshop's front (BOOKSHOP_PLAN §4 "Home"): new arrivals, the
 * categories that have something in them, and the shops. Featured strips
 * and best sellers need the office's merchandising and sales (B7); a
 * category or shop with nothing for sale is not shown — an empty shelf is
 * not a shop window.
 */
class PresentShopHomeAction
{
    /**
     * @return array{new_arrivals: list<array<string, mixed>>, categories: list<array<string, mixed>>, vendors: list<array<string, mixed>>}
     */
    public function execute(): array
    {
        $shopWide = fn () => ListShopProductsAction::forSale()->where('visibility', ProductVisibility::Shop->value);

        $newArrivals = $shopWide()
            ->with(['images', 'variants', 'vendor', 'category'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Product $p) => ShopPresenter::card($p))
            ->values()->all();

        $counts = $shopWide()
            ->selectRaw('product_category_id, count(*) as aggregate')
            ->whereNotNull('product_category_id')
            ->groupBy('product_category_id')
            ->pluck('aggregate', 'product_category_id');

        $locale = app()->getLocale();
        $categories = ProductCategory::query()
            ->where('is_active', true)
            ->whereIn('id', $counts->keys()->all())
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(fn (ProductCategory $c) => [
                'slug' => $c->slug,
                'name' => ($locale === 'dv' && $c->name_dv) ? $c->name_dv : (($locale === 'ar' && $c->name_ar) ? $c->name_ar : $c->name),
                'count' => (int) $counts->get($c->id, 0),
            ])->values()->all();

        $vendors = Vendor::query()
            ->where('status', VendorStatus::Active->value)
            ->withCount(['products as for_sale_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->get()
            ->filter(fn (Vendor $v) => $v->for_sale_count > 0)
            ->map(fn (Vendor $v) => ShopPresenter::vendor($v) + ['count' => (int) $v->for_sale_count])
            ->values()->all();

        return ['new_arrivals' => $newArrivals, 'categories' => $categories, 'vendors' => $vendors];
    }
}
