<?php

namespace App\Domains\Circulation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookTitle extends Model
{
    protected $fillable = ['title', 'author', 'isbn', 'classification', 'language', 'loan_days', 'notes'];

    protected $casts = ['loan_days' => 'integer'];

    /** @return HasMany<BookCopy, $this> */
    public function copies(): HasMany
    {
        return $this->hasMany(BookCopy::class);
    }
}
