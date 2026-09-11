<?php

namespace App\Domains\People\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One occasion on which somebody read a child's sensitive notes.
 *
 * Append-only in practice: nothing updates or deletes these rows, because the
 * whole point is answering "who looked, and when?" — a question that cannot be
 * answered retrospectively if it was never recorded.
 */
class SensitiveNoteView extends Model
{
    protected $fillable = ['student_id', 'viewed_by', 'viewed_at'];

    protected $casts = ['viewed_at' => 'datetime'];
}
