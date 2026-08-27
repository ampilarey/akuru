<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LIBRARY_PLAN §35.3 — one row per (user, item). Reader notes/progress are
 * private to the reader (business rule §43.8).
 */
class LibraryReadingProgress extends Model
{
    protected $table = 'library_reading_progress';

    protected $fillable = [
        'user_id',
        'library_item_id',
        'current_page',
        'progress_percent',
        'last_read_at',
        'completed_at',
        'total_reading_seconds',
    ];

    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(LibraryItem::class, 'library_item_id');
    }
}
