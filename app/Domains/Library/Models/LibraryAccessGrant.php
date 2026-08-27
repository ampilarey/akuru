<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LIBRARY_PLAN §35.4 — the single source of "may this user read this
 * non-free item". Rule §43.10: admin can revoke by setting status.
 */
class LibraryAccessGrant extends Model
{
    protected $fillable = [
        'user_id',
        'library_item_id',
        'access_type',
        'source_type',
        'source_id',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(LibraryItem::class, 'library_item_id');
    }
}
