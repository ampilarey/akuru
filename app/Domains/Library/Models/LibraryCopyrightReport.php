<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** BP3 (BACKLOG B11): a report that a Library work copies the reporter's own — open, upheld or dismissed. */
class LibraryCopyrightReport extends Model
{
    public const STATUSES = ['open', 'upheld', 'dismissed'];

    protected $fillable = [
        'library_item_id', 'reporter_user_id', 'name', 'email', 'work_claimed', 'statement',
        'status', 'decided_by', 'decided_at', 'decision_note', 'academic_year_id',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(LibraryItem::class, 'library_item_id');
    }
}
