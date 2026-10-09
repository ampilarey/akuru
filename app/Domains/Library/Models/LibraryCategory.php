<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryCategory extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'name_dv',
        'name_ar',
        'slug',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LibraryItem::class, 'library_category_id');
    }

    /**
     * LT6: the name a reader sees — the office's Dhivehi or Arabic where it gave
     * one for that language, the English name otherwise.
     */
    public function nameIn(string $locale): string
    {
        $name = match ($locale) {
            'dv' => $this->name_dv,
            'ar' => $this->name_ar,
            default => null,
        };

        return filled($name) ? (string) $name : (string) $this->name;
    }
}
