<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Enums\ProductVisibility;
use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\Vendor;

/**
 * The shop's public addresses for the sitemap (BOOKSHOP_PLAN §6.7): the
 * home, each category with something in it, each active vendor's page, and
 * every product for sale. Paths are relative to the locale prefix.
 */
class ListShopSitemapEntriesAction
{
    /**
     * @return list<array{path: string, lastmod: string, priority: string}>
     */
    public function execute(): array
    {
        $entries = [['path' => 'shop', 'lastmod' => now()->toDateString(), 'priority' => '0.8']];

        $products = ListShopProductsAction::forSale()->orderByDesc('updated_at')->get(['id', 'slug', 'vendor_id', 'product_category_id', 'visibility', 'updated_at']);

        $categoryIds = $products->where('visibility', ProductVisibility::Shop)->pluck('product_category_id')->filter()->unique()->all();
        foreach (ProductCategory::query()->where('is_active', true)->whereIn('id', $categoryIds)->get(['slug', 'updated_at']) as $category) {
            $entries[] = ['path' => 'shop/c/'.$category->slug, 'lastmod' => $category->updated_at?->toDateString() ?? now()->toDateString(), 'priority' => '0.6'];
        }

        foreach (Vendor::query()->where('status', VendorStatus::Active->value)->whereIn('id', $products->pluck('vendor_id')->unique()->all())->get(['slug', 'updated_at']) as $vendor) {
            $entries[] = ['path' => 'shop/'.$vendor->slug, 'lastmod' => $vendor->updated_at?->toDateString() ?? now()->toDateString(), 'priority' => '0.6'];
        }

        foreach ($products as $product) {
            /** @var Product $product */
            $entries[] = ['path' => 'shop/products/'.$product->slug, 'lastmod' => $product->updated_at?->toDateString() ?? now()->toDateString(), 'priority' => '0.7'];
        }

        return $entries;
    }
}
