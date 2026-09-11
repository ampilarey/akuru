<?php

namespace App\Domains\Academics\Models;

use App\Domains\Academics\Enums\FoundItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FoundItem extends Model
{
    protected $fillable = [
        'academic_year_id',
        'logged_by',
        'title',
        'description',
        'location',
        'found_at',
        'held_at',
        'status',
        'returned_at',
        'returned_by',
        'returned_to',
        'photo_media_id',
    ];

    protected $casts = [
        'found_at' => 'date',
        'returned_at' => 'datetime',
        'status' => FoundItemStatus::class,
    ];

    /** What a family should see: still here, and from this year. */
    public function scopeStillHere(Builder $query): Builder
    {
        return $query->where('status', FoundItemStatus::Listed->value);
    }
}
