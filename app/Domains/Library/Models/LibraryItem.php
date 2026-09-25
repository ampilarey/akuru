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
        'cover_media_file_id',
        'body',
        'toc',
        'citations',
        'affiliation',
        'research_field',
        'suggested_reviewer',
        'declarations',
        'declared_at',
        'pdf_media_file_id',
        'status',
        'featured',
        'featured_at',
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
            'submitted_at' => 'datetime',
            'preview_enabled' => 'boolean',
            'price' => 'decimal:2',
            'declarations' => 'array',
            'declared_at' => 'datetime',
            'featured' => 'boolean',
            'featured_at' => 'datetime',
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

    /** The reader's pages, made by `SyncLibraryItemPagesAction` from the body or the PDF. */
    public function pages(): HasMany
    {
        return $this->hasMany(LibraryItemPage::class)->orderBy('page_number');
    }

    /** One row per reader who opened it (§8.2 "most read"). */
    public function readers(): HasMany
    {
        return $this->hasMany(LibraryReadingProgress::class);
    }

    /** Paid purchases (§8.2 "most purchased"). */
    public function paidPurchases(): HasMany
    {
        return $this->hasMany(LibraryPurchase::class)->where('status', 'paid');
    }

    /** The approved writer who submitted it, when it came through `/write`. */
    public function writer(): BelongsTo
    {
        return $this->belongsTo(WriterProfile::class, 'writer_id');
    }
}
