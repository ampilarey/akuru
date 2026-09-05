<?php

namespace App\Domains\Academics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pupil's own "I have done this" on a piece of homework.
 *
 * Deliberately invisible to grading: it is the pupil's checklist, not a mark.
 * EduPage does the same for text homework, and the moment a self-tick counts
 * towards anything, pupils stop telling the truth with it.
 */
class HomeworkTick extends Model
{
    protected $fillable = [
        'lesson_log_id',
        'student_id',
        'academic_year_id',
        'ticked_at',
    ];

    protected $casts = [
        'ticked_at' => 'datetime',
    ];

    public function lessonLog(): BelongsTo
    {
        return $this->belongsTo(LessonLog::class);
    }
}
