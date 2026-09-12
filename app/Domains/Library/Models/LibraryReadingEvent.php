<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only. Nothing in the application updates or deletes a row here except
 * the age-based prune — a reading event is evidence, and evidence that can be
 * edited is not evidence.
 */
class LibraryReadingEvent extends Model
{
    protected $fillable = [
        'user_id', 'library_item_id', 'page_number',
        'session_hash', 'device_hash', 'academic_year_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
