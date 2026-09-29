<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Enums\ProductVisibility;
use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\ShopHomeFeature;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorCollection;
use App\Domains\Bookshop\Support\Merchandise;
use App\Domains\Bookshop\Support\ShopPresenter;
use App\Domains\Media\Actions\ResolvePublicImageVariantAction;

/**
 * The bookshop's front (BOOKSHOP_PLAN §4 "Home"): since B7 the office's
 * hero slides, featured products and featured collections (§7), then best
 * sellers, new arrivals, the categories that have something in them, and
 * the shops. A category with nothing for sale is not shown, and a featured
 * product or collection that is no longer for sale drops out — an empty
 * shelf is not a shop window. Every open shop is listed, though, even one
 * with nothing on sale yet (STATUS §5kw): a customer looking for a shop by
 * name should find it.
 */
class PresentShopHomeAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $shopWide = fn () => ListShopProductsAction::forSale()->where('visibility', ProductVisibility::Shop->value);
        $cards = fn ($query) => $query->with(['images', 'variants', 'vendor', 'category'])->get()->map(fn (Product $p) => ShopPresenter::card($p))->values()->all();
        $features = ShopHomeFeature::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()->groupBy('kind');

        $newArrivals = $cards($shopWide()->orderByDesc('created_at')->orderByDesc('id')->limit(8));

        $inOrder = function (array $ids) use ($shopWide): array {
            if ($ids === []) {
                return [];
            }
            $products = $shopWide()->whereIn('id', $ids)->with(['images', 'variants', 'vendor', 'category'])->get()->keyBy('id');

            return collect($ids)->map(fn (int $id) => $products->get($id))->filter()->map(fn (Product $p) => ShopPresenter::card($p))->values()->all();
        };
        $best = $inOrder(Merchandise::bestSellerIds());
        $featured = $inOrder($features->get('product', collect())->pluck('product_id')->map(fn ($id) => (int) $id)->all());

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

        // Every open shop is listed (STATUS §5kw: the owner could not find
        // Fitrah, which had nothing listed yet on production). A shop with
        // products comes first; one without says it is opening soon. Its
        // published logo shows when it has one.
        $storefronts = app(ResolveStorefrontAction::class);
        $vendors = Vendor::query()
            ->where('status', VendorStatus::Active->value)
            ->with('storefront')
            ->withCount(['products as for_sale_count' => fn ($q) => $q->where('status', 'active')->where('visibility', ProductVisibility::Shop->value)])
            ->orderBy('name')
            ->get()
            ->sortBy(fn (Vendor $v) => $v->for_sale_count > 0 ? 0 : 1)
            ->map(function (Vendor $v) use ($storefronts) {
                $storefront = $storefronts->execute($v);

                return ShopPresenter::vendor($v) + [
                    'count' => (int) $v->for_sale_count,
                    'logo' => $storefront['logo'] ?? null,
                    'display_name' => $storefront['name'] ?? $v->name,
                ];
            })
            ->values()->all();

        return [
            'hero' => $this->hero($features->get('hero', collect())),
            // §5lb: the timed sales ending soonest.
            'deals' => $cards(ListShopProductsAction::onSale($shopWide())->orderBy('sale_ends_at')->orderBy('id')->limit(8)),
            'featured' => $featured,
            'collections' => $this->collections($features->get('collection', collect())),
            'best_sellers' => $best,
            'new_arrivals' => $newArrivals,
            'categories' => $categories,
            'vendors' => $vendors,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function hero($slides): array
    {
        $images = app(ResolvePublicImageVariantAction::class);

        return $slides->map(fn (ShopHomeFeature $f) => [
            'heading' => $f->localized('heading'),
            'subheading' => $f->localized('subheading'),
            'image' => $f->media_file_id !== null ? $images->execute((int) $f->media_file_id, 1600) : null,
            'url' => $this->url((array) ($f->link ?? [])),
        ])->filter(fn (array $s) => $s['heading'] !== null)->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collections($features): array
    {
        $out = [];
        foreach ($features as $f) {
            /** @var ShopHomeFeature $f */
            $collection = VendorCollection::query()->whereKey($f->vendor_collection_id)->where('is_active', true)
                ->whereHas('vendor', fn ($v) => $v->where('status', VendorStatus::Active->value))->with('vendor')->first();
            if ($collection === null) {
                continue;
            }
            $cards = $collection->forSaleQuery()->with(['images', 'variants', 'vendor', 'category'])->limit(4)->get()->map(fn (Product $p) => ShopPresenter::card($p))->values()->all();
            if ($cards === []) {
                continue;
            }
            $out[] = [
                'name' => $f->localized('heading') ?? $collection->localizedName(),
                'vendor' => $collection->vendor->name,
                'url' => route('public.shop.vendor.collection', [$collection->vendor->slug, $collection->slug]),
                'cards' => $cards,
            ];
        }

        return $out;
    }

    /** A hero button's address, or null when its target is gone. */
    private function url(array $link): ?string
    {
        $target = (string) ($link['target'] ?? '');

        return match ($link['kind'] ?? null) {
            'vendor' => Vendor::query()->where('slug', $target)->where('status', VendorStatus::Active->value)->exists() ? route('public.shop.vendor', $target) : null,
            'category' => ProductCategory::query()->where('slug', $target)->where('is_active', true)->exists() ? route('public.shop.category', $target) : null,
            'product' => ListShopProductsAction::forSale()->where('slug', $target)->exists() ? route('public.shop.product', $target) : null,
            'collection' => ($c = VendorCollection::query()->whereKey((int) $target)->where('is_active', true)->with('vendor')->first()) !== null
                ? route('public.shop.vendor.collection', [$c->vendor->slug, $c->slug]) : null,
            default => null,
        };
    }
}
