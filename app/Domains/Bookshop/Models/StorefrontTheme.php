<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A look in the theme gallery (BOOKSHOP_PLAN B10d, ADR-039): theme data and
 * optional cleaned CSS, published by the office for any shop to apply.
 */
class StorefrontTheme extends Model
{
    public const STATUSES = ['submitted', 'published', 'declined', 'withdrawn'];

    protected $fillable = [
        'slug', 'name', 'description', 'theme', 'custom_css', 'status', 'source', 'source_vendor_id',
        'submitted_by', 'reviewed_by', 'reviewed_at', 'review_note', 'uses_count',
    ];

    protected function casts(): array
    {
        return ['theme' => 'array', 'reviewed_at' => 'datetime', 'uses_count' => 'integer'];
    }

    public function sourceVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'source_vendor_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function present(): array
    {
        $theme = (array) $this->theme;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'colors' => (array) ($theme['colors'] ?? []),
            'fonts' => ['heading' => $theme['fonts']['heading'] ?? null, 'body' => $theme['fonts']['body'] ?? null],
            'shape' => (array) ($theme['shape'] ?? []),
            'has_css' => $this->custom_css !== null && $this->custom_css !== '',
            'css' => $this->custom_css,
            'source' => $this->source,
            'by' => $this->sourceVendor?->name,
            'uses' => $this->uses_count,
            'note' => $this->review_note,
            'submitted_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
