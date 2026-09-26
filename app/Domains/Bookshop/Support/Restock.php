<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductVariant;

/**
 * Goods coming back onto the shelf (B3): a cancelled order's items, or a
 * returned item the shop can sell again. Only counted stock moves; a
 * product the shop does not count, or one deleted since, is left alone.
 * The mirror of `MarkCheckoutPaidAction::takeStock`.
 */
final class Restock
{
    public static function item(OrderItem $item, int $quantity): void
    {
        if ($quantity <= 0 || $item->product_id === null) {
            return;
        }
        $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->first();
        if ($product === null || ! $product->track_stock) {
            return;
        }
        if ($item->product_variant_id !== null) {
            $variant = ProductVariant::query()->whereKey($item->product_variant_id)->lockForUpdate()->first();
            $variant?->update(['stock' => (int) $variant->stock + $quantity]);

            return;
        }
        $product->update(['stock' => (int) $product->stock + $quantity]);
    }
}
