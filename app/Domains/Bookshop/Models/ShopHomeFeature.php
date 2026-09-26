<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The office's merchandising of the shop home (BOOKSHOP_PLAN §7
 * "featured on the shop home; collections on the shop home; the shop
 * home's hero"): a hero slide, a featured product, or a featured
 * collection, in the office's order.
 */
class ShopHomeFeature extends Model
{
    public const KINDS = ['hero', 'product', 'collection'];

    protected $fillable = [
        'kind', 'product_id', 'vendor_collection_id', 'heading', 'heading_dv', 'heading_ar', 'subheading', 'subheading_dv',
        'subheading_ar', 'media_file_id', 'link', 'sort_order', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return ['link' => 'array', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(VendorCollection::class, 'vendor_collection_id');
    }

    /** The field in the visitor's language when given, else the English one. */
    public function localized(string $field): ?string
    {
        $locale = app()->getLocale();
        $value = in_array($locale, ['dv', 'ar'], true) ? $this->getAttribute($field.'_'.$locale) : null;
        $value = is_string($value) && $value !== '' ? $value : $this->getAttribute($field);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
