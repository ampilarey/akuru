<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LibraryTag extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(LibraryItem::class, 'library_item_tag');
    }
}
