<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * B14: one row per shelf search — the term and how many items it found.
 * Append-only; pruned by age with the reading events.
 */
class LibrarySearchLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['term', 'hits', 'user_id', 'created_at'];

    protected function casts(): array
    {
        return ['hits' => 'integer', 'created_at' => 'datetime'];
    }
}
