<?php

namespace App\Domains\Bookshop\Models;

use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named group of a vendor's products (BOOKSHOP_PLAN §5 "Collections"):
 * hand-picked through the pivot, or by rule — a tag, a category, or both.
 * Shown by a section and at `/shop/<vendor>/<collection>`.
 */
class VendorCollection extends Model
{
    protected $fillable = ['vendor_id', 'slug', 'name', 'name_dv', 'name_ar', 'description', 'rule', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'rule' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'vendor_collection_products')->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function isManual(): bool
    {
        return empty($this->rule);
    }

    public function localizedName(): string
    {
        $locale = app()->getLocale();
        $value = in_array($locale, ['dv', 'ar'], true) ? $this->getAttribute('name_'.$locale) : null;

        return is_string($value) && $value !== '' ? $value : (string) $this->name;
    }

    /** This collection's products for sale, in its order (hand-picked) or newest first (by rule). */
    public function forSaleQuery(): Builder
    {
        $query = ListShopProductsAction::forSale()->where('vendor_id', $this->vendor_id);
        if ($this->isManual()) {
            $ids = $this->products()->pluck('products.id')->all();

            return $query->whereIn('id', $ids === [] ? [0] : $ids)->orderByRaw('field(id, '.implode(',', $ids === [] ? [0] : array_map('intval', $ids)).')');
        }
        $rule = (array) $this->rule;
        $tags = array_values(array_filter(array_map('strval', (array) ($rule['tags'] ?? []))));

        return $query
            ->when(! empty($rule['category_id']), fn ($q) => $q->where('product_category_id', (int) $rule['category_id']))
            ->when($tags !== [], fn ($q) => $q->where(function ($w) use ($tags) {
                foreach ($tags as $tag) {
                    $w->orWhereJsonContains('tags', $tag);
                }
            }))
            ->orderByDesc('created_at');
    }
}
