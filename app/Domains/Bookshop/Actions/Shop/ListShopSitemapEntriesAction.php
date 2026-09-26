<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Enums\ProductVisibility;
use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorCollection;
use App\Domains\Bookshop\Models\VendorPage;

/**
 * The shop's public addresses for the sitemap (BOOKSHOP_PLAN §6.7): the
 * home, each category with something in it, each active vendor's page, its
 * published pages and active collections (B5), and every product for
 * sale. Paths are relative to the locale prefix.
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

        $vendors = Vendor::query()->where('status', VendorStatus::Active->value)->whereIn('id', $products->pluck('vendor_id')->unique()->all())->with('storefront')->get(['id', 'slug', 'updated_at']);
        foreach ($vendors as $vendor) {
            $entries[] = ['path' => 'shop/'.$vendor->slug, 'lastmod' => $vendor->updated_at?->toDateString() ?? now()->toDateString(), 'priority' => '0.6'];
            if ($vendor->storefront === null || ! $vendor->storefront->isLive()) {
                continue;
            }
            foreach (VendorPage::query()->where('vendor_id', $vendor->id)->whereNotNull('published_at')->get(['slug', 'published_at']) as $page) {
                $entries[] = ['path' => 'shop/'.$vendor->slug.'/p/'.$page->slug, 'lastmod' => $page->published_at?->toDateString() ?? now()->toDateString(), 'priority' => '0.5'];
            }
            foreach (VendorCollection::query()->where('vendor_id', $vendor->id)->where('is_active', true)->get(['slug', 'updated_at']) as $collection) {
                $entries[] = ['path' => 'shop/'.$vendor->slug.'/'.$collection->slug, 'lastmod' => $collection->updated_at?->toDateString() ?? now()->toDateString(), 'priority' => '0.5'];
            }
        }

        foreach ($products as $product) {
            /** @var Product $product */
            $entries[] = ['path' => 'shop/products/'.$product->slug, 'lastmod' => $product->updated_at?->toDateString() ?? now()->toDateString(), 'priority' => '0.7'];
        }

        return $entries;
    }
}
