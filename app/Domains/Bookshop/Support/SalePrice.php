<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Models\Product;
use Carbon\CarbonInterface;

/**
 * A timed sale (STATUS §5lb): a percentage off a product's list price — and
 * its variants' — from `sale_starts_at` (or at once) until `sale_ends_at`.
 * A sale always has an end, so a forgotten one cannot run for ever. One rule
 * for the card, the product page, the deals page, the cart and the checkout,
 * so what is shown is what is charged. A discount code applies on top, to
 * the sale price, the same as to any other price.
 */
final class SalePrice
{
    public const MAX_PERCENT = 90;

    public static function active(Product $product, ?CarbonInterface $now = null): bool
    {
        $now ??= now();
        $percent = (int) $product->sale_percent;

        return $percent > 0 && $percent <= self::MAX_PERCENT
            && $product->sale_ends_at !== null && $product->sale_ends_at->gt($now)
            && ($product->sale_starts_at === null || $product->sale_starts_at->lte($now));
    }

    /** The price after the sale while it runs, else the price given. */
    public static function apply(float $list, Product $product, ?CarbonInterface $now = null): float
    {
        return self::active($product, $now) ? round($list * (100 - (int) $product->sale_percent) / 100, 2) : $list;
    }

    /**
     * What a card and the product page say about the sale, or null.
     *
     * @return array{percent: int, ends_at: string}|null
     */
    public static function present(Product $product): ?array
    {
        return self::active($product)
            ? ['percent' => (int) $product->sale_percent, 'ends_at' => $product->sale_ends_at->toIso8601String()]
            : null;
    }
}
