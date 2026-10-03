<?php

namespace App\Domains\Courses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A reply in a course discussion topic (Moodle parity slice M3, STATUS §5oj). */
class CourseForumPost extends Model
{
    protected $fillable = [
        'topic_id',
        'academic_year_id',
        'user_id',
        'body',
        'hidden_at',
        'hidden_by',
    ];

    protected function casts(): array
    {
        return [
            'hidden_at' => 'datetime',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(CourseForumTopic::class, 'topic_id');
    }
}
