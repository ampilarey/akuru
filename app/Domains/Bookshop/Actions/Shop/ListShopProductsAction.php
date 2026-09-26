<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Enums\ProductStatus;
use App\Domains\Bookshop\Enums\ProductVisibility;
use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\VendorCollection;
use App\Domains\Bookshop\Support\Merchandise;
use App\Domains\Bookshop\Support\ShopPresenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The one catalogue (BOOKSHOP_PLAN §1, decision 2): every vendor's products
 * that are for sale, in one listing, filtered and sorted from the query
 * string so a filtered page is a shareable link (§4).
 *
 * What the public ever sees: a product that is `active`, of a vendor that
 * is `active`. Shop-wide listings show only `shop` visibility; a vendor's
 * own page (`vendor` filter with `storefront: true`) also shows the
 * products it keeps for its page alone.
 */
class ListShopProductsAction
{
    public const SORTS = ['newest', 'best_selling', 'top_rated', 'price_asc', 'price_desc', 'name'];

    /** The query-string keys the listing understands. */
    public const FILTERS = ['q', 'category', 'vendor', 'collection', 'brand', 'price_min', 'price_max', 'in_stock', 'language', 'age', 'grade', 'sort'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $filters = [], int $perPage = 24, bool $storefront = false): LengthAwarePaginator
    {
        return $this->query($filters, $storefront)
            ->with(['images', 'variants', 'vendor', 'category'])
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Product $product) => ShopPresenter::card($product));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters = [], bool $storefront = false): Builder
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $sort = in_array($filters['sort'] ?? null, self::SORTS, true) ? $filters['sort'] : 'newest';
        // B5: a vendor's collection (§5), by rule or hand-picked — in the
        // vendor's own order unless the visitor chose a sort.
        $collection = ($filters['collection'] ?? '') !== '' && ($filters['vendor'] ?? '') !== ''
            ? VendorCollection::query()->where('slug', (string) $filters['collection'])->where('is_active', true)->whereHas('vendor', fn ($v) => $v->where('slug', (string) $filters['vendor']))->first()
            : null;
        $picked = $collection?->isManual() ? $collection->products()->pluck('products.id')->map(fn ($id) => (int) $id)->all() : [];

        $query = self::forSale()
            ->when(($filters['collection'] ?? '') !== '', fn ($query) => $collection === null ? $query->whereRaw('0 = 1') : $query->whereIn('id', $collection->forSaleQuery()->reorder()->select('id')))
            ->when(! $storefront, fn ($query) => $query->where('visibility', ProductVisibility::Shop->value))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('title', 'like', '%'.$q.'%')
                ->orWhere('title_dv', 'like', '%'.$q.'%')
                ->orWhere('title_ar', 'like', '%'.$q.'%')
                ->orWhere('summary', 'like', '%'.$q.'%')
                ->orWhere('description', 'like', '%'.$q.'%')
                ->orWhere('sku', 'like', '%'.$q.'%')
                ->orWhere('barcode', 'like', '%'.$q.'%')
                ->orWhere('tags', 'like', '%'.$q.'%')
                ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', '%'.$q.'%'))))
            ->when(($filters['category'] ?? '') !== '', fn ($query) => $query->whereIn('product_category_id', $this->categoryIds((string) $filters['category'])))
            ->when(($filters['vendor'] ?? '') !== '', fn ($query) => $query->whereHas('vendor', fn ($v) => $v->where('slug', (string) $filters['vendor'])))
            ->when(($filters['brand'] ?? '') !== '', fn ($query) => $query->whereHas('brand', fn ($b) => $b->where('slug', (string) $filters['brand'])))
            ->when(is_numeric($filters['price_min'] ?? null), fn ($query) => $query->where('price', '>=', (float) $filters['price_min']))
            ->when(is_numeric($filters['price_max'] ?? null), fn ($query) => $query->where('price', '<=', (float) $filters['price_max']))
            ->when(! empty($filters['in_stock']), fn ($query) => $query->where(fn ($w) => $w
                ->where('track_stock', false)
                ->orWhere('stock', '>', 0)
                ->orWhere('lead_days', '>', 0)
                ->orWhereHas('variants', fn ($v) => $v->where('is_active', true)->where('stock', '>', 0))))
            ->when(($filters['language'] ?? '') !== '', fn ($query) => $query->where('details->language', 'like', '%'.$filters['language'].'%'))
            ->when(($filters['age'] ?? '') !== '', fn ($query) => $query->where('details->age_range', 'like', '%'.$filters['age'].'%'))
            ->when(($filters['grade'] ?? '') !== '', fn ($query) => $query->where('details->grade', 'like', '%'.$filters['grade'].'%'));

        if ($picked !== [] && ! isset($filters['sort'])) {
            return $query->orderByRaw('field(id, '.implode(',', $picked).')');
        }

        return match ($sort) {
            // B7 (§4): by units paid for in the best-seller window, and by the published reviews' average.
            'best_selling' => $query->orderByDesc(Merchandise::unitsSold())->orderByDesc('created_at')->orderByDesc('id'),
            'top_rated' => $query->orderByRaw('rating_avg is null')->orderByDesc('rating_avg')->orderByDesc('rating_count')->orderByDesc('id'),
            'price_asc' => $query->orderBy('price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            'name' => $query->orderBy('title')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }

    /** Active products of active vendors — the rule behind every public page. */
    public static function forSale(): Builder
    {
        return Product::query()
            ->where('status', ProductStatus::Active->value)
            ->whereHas('vendor', fn ($v) => $v->where('status', VendorStatus::Active->value));
    }

    /**
     * A category and the categories inside it (one level, which is all the
     * office screen makes).
     *
     * @return list<int>
     */
    private function categoryIds(string $slug): array
    {
        $category = ProductCategory::query()->where('slug', $slug)->where('is_active', true)->first();
        if ($category === null) {
            return [0];
        }

        return [$category->id, ...ProductCategory::query()->where('parent_id', $category->id)->pluck('id')->all()];
    }
}
