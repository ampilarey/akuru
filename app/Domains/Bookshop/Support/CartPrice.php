<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Models\CartItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductVariant;

/**
 * The price a cart line is charged at (B2), and since B9d a school quote's:
 * a line that came from an accepted quote pays the quoted price while the
 * quote holds — the same product, variant and shop, not past its date.
 * Otherwise, and after it lapses, the list price. One rule for the cart
 * page and the checkout, so what is shown is what is charged.
 */
final class CartPrice
{
    public static function unit(CartItem $item, Product $product, ?ProductVariant $variant): float
    {
        $list = (float) ($variant?->price ?? $product->price);
        $quoted = self::quoted($item, $product, $variant);

        return $quoted ?? $list;
    }

    public static function quoted(CartItem $item, Product $product, ?ProductVariant $variant): ?float
    {
        if ($item->quote_item_id === null) {
            return null;
        }
        $line = $item->quoteItem()->with('quote')->first();
        if ($line === null || $line->quoted_price === null || ! $line->quote?->priceHolds()
            || (int) $line->product_id !== (int) $product->id || (int) ($line->product_variant_id ?? 0) !== (int) ($variant?->id ?? 0)
            || (int) $line->quote->vendor_id !== (int) $product->vendor_id) {
            return null;
        }

        return (float) $line->quoted_price;
    }
}
