<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A vendor's storefront design (BOOKSHOP_PLAN §6): identity, theme,
 * sections, menu and SEO as data, a draft the vendor edits and a published
 * copy the public page renders. The office may hold it (§6.6): while held
 * the public sees the plain page and publishing is refused.
 */
class VendorStorefront extends Model
{
    protected $fillable = [
        'vendor_id', 'draft_identity', 'draft_theme', 'draft_sections', 'draft_navigation', 'draft_seo',
        'published_identity', 'published_theme', 'published_sections', 'published_navigation', 'published_seo',
        'published_version_id', 'published_at', 'published_by',
        'held_at', 'held_by', 'moderation_note', 'locked_section_types',
        'custom_css', 'custom_css_pending', 'custom_css_status', 'custom_css_note', 'custom_css_submitted_at', 'custom_css_reviewed_at', 'custom_css_reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'draft_identity' => 'array',
            'draft_theme' => 'array',
            'draft_sections' => 'array',
            'draft_navigation' => 'array',
            'draft_seo' => 'array',
            'published_identity' => 'array',
            'published_theme' => 'array',
            'published_sections' => 'array',
            'published_navigation' => 'array',
            'published_seo' => 'array',
            'locked_section_types' => 'array',
            'published_at' => 'datetime',
            'held_at' => 'datetime',
            'custom_css_submitted_at' => 'datetime',
            'custom_css_reviewed_at' => 'datetime',
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

    public function isHeld(): bool
    {
        return $this->held_at !== null;
    }

    /** Publicly visible: published and not held by the office. */
    public function isLive(): bool
    {
        return $this->isPublished() && ! $this->isHeld();
    }
}
