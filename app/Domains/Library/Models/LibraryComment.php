<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** BLOG_PLAN BL5: a comment on a blog post, or the owner's reply (STATUS §5sl). */
class LibraryComment extends Model
{
    public const STATUSES = ['pending', 'published', 'refused', 'hidden', 'reported'];

    protected $fillable = ['library_item_id', 'user_id', 'parent_id', 'body', 'status', 'report_count', 'moderated_by', 'moderated_at', 'decision_note'];

    protected function casts(): array
    {
        return ['moderated_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(LibraryItem::class, 'library_item_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
