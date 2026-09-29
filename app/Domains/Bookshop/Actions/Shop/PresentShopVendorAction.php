<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorCollection;
use App\Domains\Bookshop\Models\VendorPage;
use App\Domains\Bookshop\Support\ShopPresenter;

/**
 * A vendor's page head (BOOKSHOP_PLAN §2 `/shop/<vendor>`): its name, its
 * tagline and "at Akuru Bookstore" (decision 11), and — since B4 — its
 * published storefront (identity and theme), or the draft for the vendor's
 * own preview. Since B5 also a page under the storefront (§6.4) and a
 * collection (§5). Null for an unknown or suspended vendor, an unpublished
 * page or an inactive collection. Without a published storefront the page
 * stays the plain B1b one.
 */
class PresentShopVendorAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(string $slug, bool $draft = false): ?array
    {
        $vendor = $this->vendor($slug);
        if ($vendor === null) {
            return null;
        }

        return ShopPresenter::vendor($vendor) + ['storefront' => app(ResolveStorefrontAction::class)->execute($vendor, $draft)];
    }

    /**
     * @return array{vendor: array<string, mixed>, page: array<string, mixed>}|null
     */
    public function page(string $slug, string $pageSlug, bool $draft = false): ?array
    {
        $vendor = $this->vendor($slug);
        $page = $vendor === null ? null : VendorPage::query()->where('vendor_id', $vendor->id)->where('slug', $pageSlug)->first();
        $storefront = $vendor === null ? null : app(ResolveStorefrontAction::class)->execute($vendor, $draft);
        $rendered = $page === null || $storefront === null ? null : app(ResolveStorefrontAction::class)->page($vendor, $page, $draft);
        if ($rendered === null) {
            return null;
        }

        return ['vendor' => ShopPresenter::vendor($vendor) + ['storefront' => $storefront], 'page' => $rendered];
    }

    /**
     * @return array{vendor: array<string, mixed>, collection: array<string, mixed>}|null
     */
    public function collection(string $slug, string $collectionSlug): ?array
    {
        $vendor = $this->vendor($slug);
        $collection = $vendor === null ? null : VendorCollection::query()->where('vendor_id', $vendor->id)->where('slug', $collectionSlug)->where('is_active', true)->first();
        if ($collection === null) {
            return null;
        }

        return [
            'vendor' => ShopPresenter::vendor($vendor) + ['storefront' => app(ResolveStorefrontAction::class)->execute($vendor)],
            'collection' => ['slug' => $collection->slug, 'name' => $collection->localizedName(), 'description' => $collection->description, 'manual' => $collection->isManual()]
                + ['book_list' => $collection->book_list && $collection->isManual() ? $this->bookList($collection) : null],
        ];
    }

    /**
     * §5lc: a school's book list — each item with how many the list asks for,
     * its price now (a running sale's included) and whether it can be bought,
     * and what the items that can be bought come to.
     *
     * @return array<string, mixed>
     */
    public function bookList(VendorCollection $collection): array
    {
        $forSale = $collection->forSaleQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $lines = $collection->products()->with(['images', 'variants', 'vendor', 'category'])->get()
            ->map(function (Product $product) use ($forSale) {
                $card = ShopPresenter::card($product);
                $quantity = max(1, (int) ($product->pivot->quantity ?? 1));
                $buyable = in_array((int) $product->id, $forSale, true)
                    && $card['stock']['state'] !== 'out_of_stock'
                    && $product->variants->where('is_active', true)->isEmpty();

                return [
                    'slug' => $product->slug,
                    'title' => $card['title'],
                    'quantity' => $quantity,
                    'price' => $card['price'],
                    'line_total' => number_format((float) $card['price'] * $quantity, 2, '.', ''),
                    'buyable' => $buyable,
                ];
            })->values()->all();

        return [
            'school' => $collection->school,
            'grade' => $collection->grade,
            'lines' => $lines,
            'total' => number_format(collect($lines)->where('buyable', true)->sum(fn (array $l) => (float) $l['line_total']), 2, '.', ''),
            'buyable' => collect($lines)->where('buyable', true)->count(),
            'currency' => (string) config('bookshop.currency', 'MVR'),
        ];
    }

    private function vendor(string $slug): ?Vendor
    {
        return Vendor::query()->where('slug', $slug)->where('status', VendorStatus::Active->value)->with('storefront')->first();
    }
}
