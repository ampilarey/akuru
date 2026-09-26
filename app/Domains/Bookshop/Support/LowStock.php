<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductVariant;

/**
 * What is at or below its low-stock level, or sold out (BOOKSHOP_PLAN §5
 * and §7 Reports "low stock"; slice B8). Counted products on sale only —
 * a draft being prepared at zero stock is not "sold out". A variant uses
 * its product's level. One shop's for the portal, every shop's for the
 * office.
 */
final class LowStock
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(?int $vendorId = null, int $limit = 2000): array
    {
        $products = Product::query()
            ->when($vendorId !== null, fn ($q) => $q->where('vendor_id', $vendorId))
            ->where('track_stock', true)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereColumn('stock', '<=', 'low_stock_at')->orWhere('stock', '<=', 0)
                ->orWhereHas('variants', fn ($v) => $v->where('is_active', true)->where(fn ($w) => $w->where('product_variants.stock', '<=', 0)
                    ->orWhereColumn('product_variants.stock', '<=', 'products.low_stock_at'))))
            ->with(['variants', 'vendor:id,name,slug'])
            ->orderBy('stock')->orderBy('title')
            ->limit($limit)->get();

        $out = [];
        foreach ($products as $p) {
            /** @var Product $p */
            $level = $p->low_stock_at;
            if ($p->variants->isEmpty()) {
                $out[] = self::row($p, null, (int) $p->stock, $level);

                continue;
            }
            foreach ($p->variants as $v) {
                /** @var ProductVariant $v */
                if ($v->is_active && ((int) $v->stock <= 0 || ($level !== null && (int) $v->stock <= $level))) {
                    $out[] = self::row($p, $v, (int) $v->stock, $level);
                }
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(Product $p, ?ProductVariant $v, int $stock, ?int $level): array
    {
        return [
            'product_id' => $p->id,
            'variant_id' => $v?->id,
            'slug' => $p->slug,
            'title' => $p->title,
            'variant' => $v?->name,
            'sku' => $v?->sku ?? $p->sku,
            'vendor' => $p->vendor?->name,
            'vendor_slug' => $p->vendor?->slug,
            'stock' => $stock,
            'low_stock_at' => $level,
            'state' => $stock <= 0 ? 'sold_out' : 'low',
            'status' => $p->status->value,
        ];
    }
}
