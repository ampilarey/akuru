<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Models\StockReservation;

/**
 * What can be sold right now (BOOKSHOP_PLAN §8, audit finding 2): the
 * counted stock less what other customers' live checkouts are holding.
 * One definition, used by the cart, the checkout and the paid step.
 */
final class Stock
{
    /** Null means "not counted": the vendor does not track this product's stock. */
    public static function available(Product $product, ?ProductVariant $variant, ?int $ignoringCheckoutId = null): ?int
    {
        if (! $product->track_stock) {
            return null;
        }

        $counted = $variant !== null ? (int) $variant->stock : (int) $product->stock;

        $reserved = (int) StockReservation::query()
            ->where('product_id', $product->id)
            ->when($variant !== null, fn ($q) => $q->where('product_variant_id', $variant->id), fn ($q) => $q->whereNull('product_variant_id'))
            ->where('expires_at', '>', now())
            ->when($ignoringCheckoutId !== null, fn ($q) => $q->where('bookshop_checkout_id', '!=', $ignoringCheckoutId))
            ->sum('quantity');

        return max(0, $counted - $reserved);
    }

    /** Made to order sells without stock. */
    public static function madeToOrder(Product $product): bool
    {
        return $product->lead_days !== null && (int) $product->lead_days > 0;
    }
}
