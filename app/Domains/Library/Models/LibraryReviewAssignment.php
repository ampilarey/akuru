<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryReviewAssignment extends Model
{
    /** R3b: a report is due this many days after assignment unless the office says otherwise. */
    public const DEFAULT_DUE_DAYS = 14;

    protected $fillable = [
        'library_item_id',
        'reviewer_user_id',
        'assigned_by',
        'status',
        'recommendation',
        // R3: the review round this assignment belongs to.
        'round',
        // R3b: when the report is due, the last reminder, and the reviewer's
        // conflict-of-interest declaration.
        'due_at',
        'reminded_at',
        'coi_declared_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'reminded_at' => 'datetime',
            'coi_declared_at' => 'datetime',
        ];
    }
}
