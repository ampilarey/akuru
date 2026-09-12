<?php

namespace App\Domains\Offerings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SPEC §28.4's audit record for a deliberate re-pin.
 *
 * Append-only by design: nothing in the app updates or deletes a row here, and
 * a record that can be rewritten is not an audit trail.
 */
class OfferingRepinEvent extends Model
{
    protected $fillable = [
        'course_offering_id',
        'academic_year_id',
        'old_pinned_revision_json',
        'new_pinned_revision_json',
        'old_pin_mode',
        'new_pin_mode',
        'changed_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'old_pinned_revision_json' => 'array',
            'new_pinned_revision_json' => 'array',
        ];
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }
}
