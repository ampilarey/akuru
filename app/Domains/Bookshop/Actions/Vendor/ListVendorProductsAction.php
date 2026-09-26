<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductImage;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Media\Actions\ResolvePublicMediaUrlAction;

/**
 * A vendor's own products, for the portal and its CSV (BOOKSHOP_PLAN §5).
 * Only the scope's vendor's rows, ever.
 */
class ListVendorProductsAction
{
    /**
     * @param  array{q?: ?string, status?: ?string}  $filters
     * @return list<array<string, mixed>>
     */
    public function execute(VendorScope $scope, array $filters = [], int $limit = 500): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? '');

        $products = Product::query()
            ->where('vendor_id', $scope->vendorId)
            ->with(['images', 'variants', 'category:id,name', 'brand:id,name'])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('title', 'like', '%'.$q.'%')
                ->orWhere('sku', 'like', '%'.$q.'%')
                ->orWhere('barcode', 'like', '%'.$q.'%')))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $urls = app(ResolvePublicMediaUrlAction::class);

        return $products->map(fn (Product $p): array => [
            'id' => $p->id,
            'slug' => $p->slug,
            'title' => $p->title,
            'title_dv' => $p->title_dv,
            'badge' => $p->badge,
            'badge_dv' => $p->badge_dv,
            'badge_ar' => $p->badge_ar,
            'rating_avg' => $p->rating_avg !== null ? (string) $p->rating_avg : null,
            'rating_count' => (int) $p->rating_count,
            'title_ar' => $p->title_ar,
            'summary' => $p->summary,
            'summary_dv' => $p->summary_dv,
            'summary_ar' => $p->summary_ar,
            'description' => $p->description,
            'description_dv' => $p->description_dv,
            'description_ar' => $p->description_ar,
            'product_category_id' => $p->product_category_id,
            'category' => $p->category?->name,
            'brand_id' => $p->brand_id,
            'brand' => $p->brand?->name,
            'price' => (string) $p->price,
            'compare_at_price' => $p->compare_at_price !== null ? (string) $p->compare_at_price : null,
            'cost' => $p->cost !== null ? (string) $p->cost : null,
            'currency' => $p->currency,
            'tax_class' => $p->tax_class->value,
            'sku' => $p->sku,
            'barcode' => $p->barcode,
            'weight_grams' => $p->weight_grams,
            'dimensions' => $p->dimensions,
            'track_stock' => $p->track_stock,
            'stock' => (int) $p->stock,
            'low_stock_at' => $p->low_stock_at,
            'low_stock' => $p->track_stock && $p->low_stock_at !== null && $p->stock <= $p->low_stock_at,
            'lead_days' => $p->lead_days,
            'status' => $p->status->value,
            'visibility' => $p->visibility->value,
            'tags' => $p->tags ?? [],
            'details' => (object) ($p->details ?? []),
            'images' => $p->images->map(fn (ProductImage $i) => [
                'id' => $i->id,
                'url' => $urls->execute((int) $i->media_file_id),
                'alt' => $i->alt_text,
            ])->values()->all(),
            'variants' => $p->variants->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'sku' => $v->sku,
                'price' => $v->price !== null ? (string) $v->price : null,
                'stock' => (int) $v->stock,
                'is_active' => $v->is_active,
            ])->values()->all(),
            'updated_at' => $p->updated_at?->toDateTimeString(),
        ])->values()->all();
    }
}
