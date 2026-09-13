<?php

namespace App\Domains\Courses\Models;

use App\Domains\Courses\Enums\CourseReviewDecision as Decision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SPEC §35's "Reject courses" and "Request changes", and §34's "View
 * supervisor comments" — one record serving all three, because they were one
 * missing thing.
 *
 * **Append-only.** A review decision is something that happened; a later
 * decision is a new row, never an edit. Nothing in the domain updates or
 * deletes these, which is what lets the creator see what was asked of them and
 * when rather than only the most recent verdict.
 */
class CourseReviewDecision extends Model
{
    protected $fillable = [
        'course_id',
        'reviewer_id',
        'decision',
        'comment',
        'from_status',
        'to_status',
        'academic_year_id',
    ];

    protected function casts(): array
    {
        return [
            'decision' => Decision::class,
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
