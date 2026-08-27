<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only editorial log — rows are never updated or deleted. */
class LibraryItemReview extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'library_item_id',
        'reviewer_user_id',
        'decision',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
