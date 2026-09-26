<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A simple page under a storefront (BOOKSHOP_PLAN §6.4), at
 * `/shop/<vendor>/p/<slug>`, built from the same sections as the home. Its
 * draft publishes with the storefront's.
 */
class VendorPage extends Model
{
    protected $fillable = ['vendor_id', 'slug', 'title', 'title_dv', 'title_ar', 'draft_sections', 'published_sections', 'seo', 'published_at', 'sort_order'];

    protected function casts(): array
    {
        return [
            'draft_sections' => 'array',
            'published_sections' => 'array',
            'seo' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function localizedTitle(): string
    {
        $locale = app()->getLocale();
        $value = in_array($locale, ['dv', 'ar'], true) ? $this->getAttribute('title_'.$locale) : null;

        return is_string($value) && $value !== '' ? $value : (string) $this->title;
    }
}
