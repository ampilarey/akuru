<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductImage;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Media\Actions\ResolvePublicImageVariantAction;

/**
 * How the public shop shows a product (BOOKSHOP_PLAN §4): the title in the
 * visitor's language when the vendor gave one, a card-size photo, the
 * price and "was" price, and a stock state a customer can read. One place,
 * so a listing, a vendor page and a product page never disagree.
 */
final class ShopPresenter
{
    public const CARD_WIDTH = 480;

    public const LARGE_WIDTH = 1200;

    /** The product's own words in the page's language, else English. */
    public static function localized(Product $product, string $field): ?string
    {
        $locale = app()->getLocale();
        if (in_array($locale, ['dv', 'ar'], true)) {
            $value = $product->getAttribute($field.'_'.$locale);
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        $value = $product->getAttribute($field);

        return is_string($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function card(Product $product): array
    {
        $first = $product->images->first();

        return [
            'slug' => $product->slug,
            'title' => self::localized($product, 'title'),
            'summary' => self::localized($product, 'summary'),
            'price' => (string) $product->price,
            'compare_at_price' => $product->compare_at_price !== null ? (string) $product->compare_at_price : null,
            'currency' => $product->currency,
            'image' => $first instanceof ProductImage ? app(ResolvePublicImageVariantAction::class)->execute((int) $first->media_file_id, self::CARD_WIDTH) : null,
            'image_alt' => $first?->alt_text ?: $product->title,
            'stock' => self::stock($product),
            'vendor' => self::vendor($product->vendor),
            'category' => $product->category?->name,
            'on_sale' => $product->compare_at_price !== null && (float) $product->compare_at_price > (float) $product->price,
        ];
    }

    /**
     * @return array{name: string, slug: string, tagline: ?string}
     */
    public static function vendor(Vendor $vendor): array
    {
        return [
            'name' => $vendor->name,
            'slug' => $vendor->slug,
            'tagline' => $vendor->tagline,
            // B3 holiday mode: "back on <date>" is the day after the last day away.
            'holiday' => $vendor->onHoliday() ? [
                'back_on' => $vendor->holiday_until->copy()->addDay()->toDateString(),
                'notice' => $vendor->holiday_notice,
            ] : null,
        ];
    }

    /**
     * `in_stock`, `few_left` (with the count), `made_to_order` (with days),
     * `out_of_stock` or `available` (stock not counted). With variants, the
     * variants' stock is what can be sold.
     *
     * @return array{state: string, count: ?int, days: ?int}
     */
    public static function stock(Product $product): array
    {
        $days = $product->lead_days !== null && $product->lead_days > 0 ? (int) $product->lead_days : null;

        if (! $product->track_stock) {
            return ['state' => $days !== null ? 'made_to_order' : 'available', 'count' => null, 'days' => $days];
        }

        $variants = $product->relationLoaded('variants')
            ? $product->variants->filter(fn (ProductVariant $v) => $v->is_active)
            : collect();
        $count = $variants->isNotEmpty() ? (int) $variants->sum('stock') : (int) $product->stock;

        if ($count <= 0) {
            return ['state' => $days !== null ? 'made_to_order' : 'out_of_stock', 'count' => 0, 'days' => $days];
        }

        $warnAt = $product->low_stock_at !== null ? (int) $product->low_stock_at : 3;

        return ['state' => $count <= $warnAt ? 'few_left' : 'in_stock', 'count' => $count, 'days' => $days];
    }
}
