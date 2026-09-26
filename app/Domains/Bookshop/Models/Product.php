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
        'cost',
        'currency',
        'tax_class',
        'sku',
        'barcode',
        'weight_grams',
        'dimensions',
        'track_stock',
        'stock',
        'low_stock_at',
        'lead_days',
        'status',
        'visibility',
        'featured',
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
            'cost' => 'decimal:2',
            'tax_class' => TaxClass::class,
            'status' => ProductStatus::class,
            'visibility' => ProductVisibility::class,
            'track_stock' => 'boolean',
            'featured' => 'boolean',
            'tags' => 'array',
            'details' => 'array',
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
