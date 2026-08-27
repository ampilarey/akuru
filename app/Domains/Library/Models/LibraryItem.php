<?php

namespace App\Domains\Library\Models;

use App\Domains\Library\Enums\LibraryAccessType;
use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * LIBRARY_PLAN §35.1. Later-phase columns (price, writer, commission,
 * preview) live here from L1 so the table only ever grows additively.
 */
class LibraryItem extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'slug',
        'description',
        'abstract',
        'content_type',
        'access_type',
        'price',
        'currency',
        'language',
        'library_category_id',
        'cover_image',
        'body',
        'pdf_media_file_id',
        'status',
        'published_at',
        'writer_id',
        'page_count',
        'reading_time',
        'preview_enabled',
        'preview_pages',
        'commission_type',
        'commission_value',
        'created_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'content_type' => LibraryContentType::class,
            'access_type' => LibraryAccessType::class,
            'status' => LibraryItemStatus::class,
            'published_at' => 'datetime',
            'preview_enabled' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LibraryCategory::class, 'library_category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(LibraryTag::class, 'library_item_tag');
    }

    public function authors(): HasMany
    {
        return $this->hasMany(LibraryItemAuthor::class)->orderBy('sort_order');
    }
}
