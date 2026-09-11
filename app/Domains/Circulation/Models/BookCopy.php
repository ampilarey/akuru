<?php

namespace App\Domains\Circulation\Models;

use App\Domains\Circulation\Enums\CopyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookCopy extends Model
{
    protected $fillable = ['book_title_id', 'accession_number', 'status', 'shelf'];

    protected $casts = ['status' => CopyStatus::class];

    /** @return BelongsTo<BookTitle, $this> */
    public function title(): BelongsTo
    {
        return $this->belongsTo(BookTitle::class, 'book_title_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', CopyStatus::Available->value);
    }
}
