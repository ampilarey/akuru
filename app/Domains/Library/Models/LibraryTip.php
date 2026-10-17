<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BLOG_PLAN BL6a (§7.1): a tip to a blog's writer (STATUS §5sm). Money is
 * never edited: a refund is a new row that reverses this one.
 */
class LibraryTip extends Model
{
    protected $fillable = [
        'user_id', 'writer_id', 'library_item_id', 'amount', 'currency', 'method', 'payment_id', 'status',
        'reverses_tip_id', 'note', 'note_status', 'note_decided_by', 'note_decided_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'note_decided_at' => 'datetime'];
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(WriterProfile::class, 'writer_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(LibraryItem::class, 'library_item_id');
    }
}
