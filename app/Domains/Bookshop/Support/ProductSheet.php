<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductVariant;

/**
 * The shop's product sheet (BOOKSHOP_PLAN §5 "Bulk: CSV import/export of
 * products and stock", slice B8): one layout for the export, the blank
 * template and the import, so a vendor can export, change prices or stock
 * in a spreadsheet and import the same file back.
 *
 * A product is one row. Each variant is a row of its own under it, with
 * `variant` (its name) and `parent_sku` filled and only `sku`, `price` and
 * `stock` read back. `id` and `url` are for reference; `id` matches a row
 * to its product when present.
 */
final class ProductSheet
{
    public const COLUMNS = [
        'id', 'sku', 'variant', 'parent_sku', 'title', 'title_dv', 'title_ar', 'summary',
        'price', 'compare_at_price', 'cost', 'tax_class', 'barcode', 'category', 'brand', 'tags',
        'track_stock', 'stock', 'low_stock_at', 'lead_days', 'status', 'visibility', 'weight_grams',
        'author', 'publisher', 'year', 'pages', 'language', 'age_range', 'grade', 'subject', 'url',
    ];

    /** The book and educational fields, kept in the product's `details`. */
    public const DETAILS = ['author', 'publisher', 'year', 'pages', 'language', 'age_range', 'grade', 'subject'];

    /**
     * @return list<string|int|null>
     */
    public static function productRow(Product $p): array
    {
        $details = (array) ($p->details ?? []);
        $row = [
            'id' => $p->id,
            'sku' => $p->sku,
            'variant' => null,
            'parent_sku' => null,
            'title' => $p->title,
            'title_dv' => $p->title_dv,
            'title_ar' => $p->title_ar,
            'summary' => $p->summary,
            'price' => (string) $p->price,
            'compare_at_price' => $p->compare_at_price !== null ? (string) $p->compare_at_price : null,
            'cost' => $p->cost !== null ? (string) $p->cost : null,
            'tax_class' => $p->tax_class->value,
            'barcode' => $p->barcode,
            'category' => $p->category?->slug,
            'brand' => $p->brand?->name,
            'tags' => implode('; ', (array) ($p->tags ?? [])),
            'track_stock' => $p->track_stock ? 'yes' : 'no',
            'stock' => (int) $p->stock,
            'low_stock_at' => $p->low_stock_at,
            'lead_days' => $p->lead_days,
            'status' => $p->status->value,
            'visibility' => $p->visibility->value,
            'weight_grams' => $p->weight_grams,
        ];
        foreach (self::DETAILS as $key) {
            $row[$key] = $details[$key] ?? null;
        }
        $row['url'] = route('public.shop.product', $p->slug);

        return array_values($row);
    }

    /**
     * @return list<string|int|null>
     */
    public static function variantRow(Product $p, ProductVariant $v): array
    {
        $row = array_fill_keys(self::COLUMNS, null);
        $row['sku'] = $v->sku;
        $row['variant'] = $v->name;
        $row['parent_sku'] = $p->sku;
        $row['title'] = $p->title;
        $row['price'] = $v->price !== null ? (string) $v->price : null;
        $row['stock'] = (int) $v->stock;

        return array_values($row);
    }
}
