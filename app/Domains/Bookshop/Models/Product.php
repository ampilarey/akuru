<?php

namespace App\Domains\Bookshop\Models;

use App\Domains\Bookshop\Enums\ProductStatus;
use App\Domains\Bookshop\Enums\ProductVisibility;
use App\Domains\Bookshop\Enums\TaxClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical thing a vendor sells (BOOKSHOP_PLAN §5). Book fields (author,
 * publisher, year, pages, language, ISBN in `barcode`) and educational
 * fields (age range, grade, subject) live in `details`, so one table
 * carries both without a column per kind.
 */
class Product extends Model
{
    protected $fillable = [
        'vendor_id',
        'product_category_id',
        'brand_id',
        'slug',
        'title',
        'title_dv',
        'title_ar',
        'summary',
        'summary_dv',
        'summary_ar',
        'description',
        'description_dv',
        'description_ar',
        'price',
        'compare_at_price',
        'sale_percent',
        'sale_starts_at',
        'sale_ends_at',
        'cost',
        'currency',
        'tax_class',
        'sku',
        'barcode',
        'weight_grams',
        'dimensions',
        'track_stock',
        'stock',
        'stock_at_akuru',
        'low_stock_at',
        'low_stock_notified_at',
        'lead_days',
        // COMMERCE_PARITY_PLAN P8d: a pre-order until this date.
        'preorder_release_on',
        'status',
        'submitted_at',
        'review_note',
        'review_changes',
        'reviewed_by',
        'reviewed_at',
        'visibility',
        'featured',
        'badge',
        'badge_dv',
        'badge_ar',
        'rating_avg',
        'rating_count',
        'library_item_id',
        'tags',
        'details',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'sale_percent' => 'integer',
            'sale_starts_at' => 'datetime',
            'preorder_release_on' => 'date',
            'sale_ends_at' => 'datetime',
            'cost' => 'decimal:2',
            'tax_class' => TaxClass::class,
            'status' => ProductStatus::class,
            'visibility' => ProductVisibility::class,
            'track_stock' => 'boolean',
            'featured' => 'boolean',
            'rating_avg' => 'decimal:2',
            'rating_count' => 'integer',
            'low_stock_notified_at' => 'datetime',
            'tags' => 'array',
            'details' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'review_changes' => 'array',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }
}
