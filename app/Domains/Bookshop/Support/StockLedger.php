<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * BOOKSHOP_PLAN slice B8: every change to counted stock leaves a line in
 * `stock_movements` (§9 "append-only: in, sale, return, adjustment, by
 * whom"), and a product or variant that falls to its low-stock level tells
 * the shop once (§5 Reports "low stock"; decision: the product's
 * `low_stock_at` is the level for its variants too).
 *
 * Callers change the stock themselves, inside their own transaction and
 * lock, then hand the ledger the change they made. A product the shop does
 * not count leaves no line: its number means nothing.
 */
final class StockLedger
{
    public static function record(Product $product, ?ProductVariant $variant, int $quantity, string $kind, ?int $userId = null, ?int $orderId = null, ?string $note = null): void
    {
        if ($quantity === 0 || ! $product->track_stock) {
            return;
        }
        StockMovement::query()->create([
            'vendor_id' => $product->vendor_id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'kind' => in_array($kind, StockMovement::KINDS, true) ? $kind : 'adjustment',
            'quantity' => $quantity,
            'stock_after' => (int) ($variant !== null ? $variant->stock : $product->stock),
            'order_id' => $orderId,
            'note' => $note !== null ? mb_substr($note, 0, 255) : null,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
        self::checkLow($product, $variant);
    }

    /**
     * Told once on the way down; the flag clears when stock is back above
     * the level, so the next fall tells them again.
     */
    public static function checkLow(Product $product, ?ProductVariant $variant = null): void
    {
        $level = $product->low_stock_at;
        if (! $product->track_stock || $level === null) {
            return;
        }
        $row = $variant ?? $product;
        $stock = (int) $row->stock;
        if ($stock > (int) $level) {
            if ($row->low_stock_notified_at !== null) {
                $row->forceFill(['low_stock_notified_at' => null])->saveQuietly();
            }

            return;
        }
        if ($row->low_stock_notified_at !== null) {
            return;
        }
        $row->forceFill(['low_stock_notified_at' => now()])->saveQuietly();

        $vendorId = (int) $product->vendor_id;
        $title = $product->title.($variant !== null ? ' ('.$variant->name.')' : '');
        DB::afterCommit(fn () => app(NotifyBookshopUserAction::class)->vendor(
            $vendorId,
            __('shop.notice_low_stock_title'),
            $stock === 0 ? __('shop.notice_sold_out_body', ['title' => $title]) : __('shop.notice_low_stock_body', ['title' => $title, 'stock' => $stock]),
            '/vendor/stock',
            'low_stock',
        ));
    }
}
