<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Enums\ProductVisibility;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductImage;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Support\ShopPresenter;
use App\Domains\Media\Actions\ResolvePublicImageVariantAction;

/**
 * One product's public page (BOOKSHOP_PLAN §4 "Product page"): gallery,
 * price, stock, variants, the book or educational details, the vendor card
 * and a few related products. Null — a 404 — for anything not for sale: a
 * draft, an archived product, or a suspended vendor's.
 *
 * The vendor's contact details are deliberately not here: a new vendor's
 * shop email defaults to its owner's own address, and ordering (B2) is the
 * way a customer reaches a shop.
 */
class PresentShopProductAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(string $slug): ?array
    {
        $product = ListShopProductsAction::forSale()
            ->where('slug', $slug)
            ->with(['images', 'variants', 'vendor', 'category', 'brand'])
            ->first();
        if ($product === null) {
            return null;
        }

        $images = app(ResolvePublicImageVariantAction::class);
        $locale = app()->getLocale();
        $category = $product->category;

        return ShopPresenter::card($product) + [
            'description' => ShopPresenter::localized($product, 'description'),
            'category_slug' => $category?->slug,
            'category_name' => $category === null ? null : (($locale === 'dv' && $category->name_dv) ? $category->name_dv : (($locale === 'ar' && $category->name_ar) ? $category->name_ar : $category->name)),
            'brand' => $product->brand?->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'weight_grams' => $product->weight_grams,
            'dimensions' => $product->dimensions,
            'tax_class' => $product->tax_class->value,
            'details' => $product->details ?? [],
            'tags' => $product->tags ?? [],
            'gallery' => $product->images->map(fn (ProductImage $i) => [
                'card' => $images->execute((int) $i->media_file_id, ShopPresenter::CARD_WIDTH),
                'large' => $images->execute((int) $i->media_file_id, ShopPresenter::LARGE_WIDTH),
                'alt' => $i->alt_text ?: $product->title,
            ])->filter(fn (array $i) => $i['large'] !== null)->values()->all(),
            'variants' => $product->variants
                ->filter(fn (ProductVariant $v) => $v->is_active)
                ->map(fn (ProductVariant $v) => [
                    'id' => $v->id,
                    'name' => $v->name,
                    'price' => (string) ($v->price ?? $product->price),
                    'in_stock' => ! $product->track_stock || $v->stock > 0,
                ])->values()->all(),
            'related' => $this->related($product),
        ] + ['json_ld' => $this->jsonLd($product)];
    }

    /**
     * Structured data for search engines (BOOKSHOP_PLAN §6.7): name, price,
     * availability, photos, the shop as seller. Built from the same card
     * the page shows, so they never disagree.
     *
     * @return array<string, mixed>
     */
    private function jsonLd(Product $product): array
    {
        $card = ShopPresenter::card($product);
        $availability = match ($card['stock']['state']) {
            'out_of_stock' => 'https://schema.org/OutOfStock',
            'made_to_order' => 'https://schema.org/PreOrder',
            default => 'https://schema.org/InStock',
        };
        $images = $product->images->map(fn (ProductImage $i) => app(ResolvePublicImageVariantAction::class)->execute((int) $i->media_file_id, ShopPresenter::LARGE_WIDTH))->filter()->values()->all();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $card['title'],
            'description' => $card['summary'] ?: \Illuminate\Support\Str::limit(trim(strip_tags((string) ShopPresenter::localized($product, 'description'))), 300, ''),
            'image' => $images,
            'sku' => $product->sku,
            'gtin13' => is_string($product->barcode) && preg_match('/^\d{13}$/', $product->barcode) ? $product->barcode : null,
            'brand' => $product->brand !== null ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
            'offers' => [
                '@type' => 'Offer',
                'url' => route('public.shop.product', $product->slug),
                'priceCurrency' => $card['currency'],
                'price' => $card['price'],
                'availability' => $availability,
                'seller' => ['@type' => 'Organization', 'name' => $card['vendor']['name']],
            ],
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * Up to four others from the same category, shop-wide ones only.
     *
     * @return list<array<string, mixed>>
     */
    private function related(Product $product): array
    {
        if ($product->product_category_id === null) {
            return [];
        }

        return ListShopProductsAction::forSale()
            ->where('visibility', ProductVisibility::Shop->value)
            ->where('product_category_id', $product->product_category_id)
            ->whereKeyNot($product->id)
            ->with(['images', 'variants', 'vendor', 'category'])
            ->orderByDesc('created_at')
            ->limit(4)
            ->get()
            ->map(fn (Product $p) => ShopPresenter::card($p))
            ->values()->all();
    }
}
