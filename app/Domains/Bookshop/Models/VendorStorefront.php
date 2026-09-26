<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A vendor's storefront design (BOOKSHOP_PLAN §6): identity and theme as
 * data, a draft the vendor edits and a published copy the public page
 * renders. B5 adds sections and pages to the same row.
 */
class VendorStorefront extends Model
{
    protected $fillable = [
        'vendor_id', 'draft_identity', 'draft_theme', 'published_identity', 'published_theme',
        'published_version_id', 'published_at', 'published_by',
    ];

    protected function casts(): array
    {
        return [
            'draft_identity' => 'array',
            'draft_theme' => 'array',
            'published_identity' => 'array',
            'published_theme' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(VendorStorefrontVersion::class)->orderByDesc('number');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_theme !== null;
    }
}
