<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Actions\ListCatalogueOptionsAction;
use App\Domains\Bookshop\Enums\ProductVisibility;
use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The Bookstore's public catalogue as JSON (STATUS §5ll, BOOKSHOP_PLAN §16
 * item 8f): what anyone can already see on /shop, for an app or a school's
 * site to list. Read-only, no sign-in, nothing that is not on the pages.
 *
 * Every field is named here rather than passed through from the page
 * presenters, so a field added to a page for its own reasons never reaches
 * the API by accident. Not here: ids, exact stock counts, tax classes,
 * weights, the shop's contact details, anything about orders or people.
 */
class PresentCatalogueApiAction
{
    public const MAX_PER_PAGE = 50;

    /**
     * The listing, with the listing's own filters and sorts.
     *
     * @param  array<string, mixed>  $filters
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>, links: array<string, ?string>}
     */
    public function products(array $filters, int $perPage): array
    {
        /** @var LengthAwarePaginator $page */
        $page = app(ListShopProductsAction::class)->execute($filters, max(1, min(self::MAX_PER_PAGE, $perPage)));

        return [
            'data' => array_map(fn (array $card) => $this->card($card), $page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
            'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl()],
        ];
    }

    /**
     * One product, as its page shows it; null for anything not for sale.
     *
     * @return array<string, mixed>|null
     */
    public function product(string $slug): ?array
    {
        $product = app(PresentShopProductAction::class)->execute($slug);
        if ($product === null) {
            return null;
        }

        return $this->card($product) + [
            'description' => $product['description'],
            'category_slug' => $product['category_slug'],
            'brand' => $product['brand'] === null ? null : ['name' => $product['brand'], 'slug' => $product['brand_slug']],
            'sku' => $product['sku'],
            'barcode' => $product['barcode'],
            'details' => $product['details'],
            'tags' => $product['tags'],
            'images' => array_map(fn (array $i) => ['url' => $this->absolute($i['large']), 'alt' => $i['alt']], $product['gallery']),
            'variants' => array_map(fn (array $v) => ['name' => $v['name'], 'price' => $v['price'], 'in_stock' => $v['in_stock']], $product['variants']),
            'ebook_url' => $product['ebook']['url'] ?? null,
        ];
    }

    /**
     * The shops open for business, with how many products they list store-wide.
     *
     * @return list<array<string, mixed>>
     */
    public function shops(): array
    {
        return Vendor::query()
            ->where('status', VendorStatus::Active->value)
            ->withCount(['products as for_sale' => fn ($q) => $q->whereIn('id', ListShopProductsAction::forSale()->where('visibility', ProductVisibility::Shop->value)->select('id'))])
            ->orderBy('name')
            ->get()
            ->map(fn (Vendor $v) => [
                'name' => $v->name,
                'slug' => $v->slug,
                'tagline' => $v->tagline,
                'products' => (int) $v->for_sale,
                'url' => $this->link('shop/'.$v->slug),
            ])->values()->all();
    }

    /**
     * The shared categories, named in the asked language where they have it.
     *
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        $locale = app()->getLocale();
        $all = app(ListCatalogueOptionsAction::class)->execute()['categories'];
        $slugs = array_column($all, 'slug', 'id');

        return array_map(fn (array $c) => [
            'name' => ($locale === 'dv' && $c['name_dv']) ? $c['name_dv'] : (($locale === 'ar' && $c['name_ar']) ? $c['name_ar'] : $c['name']),
            'slug' => $c['slug'],
            'parent' => $c['parent_id'] !== null ? ($slugs[$c['parent_id']] ?? null) : null,
            'url' => $this->link('shop/c/'.$c['slug']),
        ], $all);
    }

    /** An app outside the site needs the whole address, never a path. */
    private function absolute(?string $url): ?string
    {
        return $url !== null && str_starts_with($url, '/') ? url($url) : $url;
    }

    /** A page's address in the asked language, as the sitemap builds them. */
    private function link(string $path): string
    {
        return url(app()->getLocale().'/'.$path);
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function card(array $card): array
    {
        return [
            'slug' => $card['slug'],
            'title' => $card['title'],
            'summary' => $card['summary'],
            'price' => $card['price'],
            'was_price' => $card['on_sale'] ? $card['compare_at_price'] : null,
            'currency' => $card['currency'],
            'sale_ends_at' => $card['sale']['ends_at'] ?? null,
            'availability' => $card['stock']['state'],
            // LENDING_AND_USED_BOOKS_PLAN U1: new, or a used book's grade.
            'condition' => $card['condition'],
            'image' => $this->absolute($card['image']),
            'image_alt' => $card['image_alt'],
            'shop' => ['name' => $card['vendor']['name'], 'slug' => $card['vendor']['slug']],
            'category' => $card['category'],
            'rating' => $card['rating'],
            'url' => $this->link('shop/products/'.$card['slug']),
        ];
    }
}
