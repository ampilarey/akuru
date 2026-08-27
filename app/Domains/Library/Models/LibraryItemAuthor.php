<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LibraryItemAuthor extends Model
{
    protected $fillable = [
        'library_item_id',
        'name',
        'user_id',
        'sort_order',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(LibraryItem::class, 'library_item_id');
    }
}
