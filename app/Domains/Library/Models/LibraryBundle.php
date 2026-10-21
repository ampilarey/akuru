<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** LIBRARY_PLAN §19 (B4d): paid works sold together at one price. */
class LibraryBundle extends Model
{
    protected $fillable = ['slug', 'title', 'title_dv', 'title_ar', 'description', 'price', 'currency', 'is_active', 'ends_at', 'created_by'];

    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean', 'ends_at' => 'datetime'];

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(LibraryItem::class, 'library_bundle_items')->withPivot('sort_order')->orderByPivot('sort_order');
    }

    public function titleIn(string $locale): string
    {
        $title = $locale === 'dv' ? $this->title_dv : ($locale === 'ar' ? $this->title_ar : null);

        return $title !== null && $title !== '' ? $title : (string) $this->title;
    }

    /** On sale now: switched on, and its end not passed. */
    public function onSale(): bool
    {
        return $this->is_active && ($this->ends_at === null || $this->ends_at->isFuture());
    }
}
