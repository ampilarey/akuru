<?php

namespace App\Domains\Courses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A discussion topic on a course (Moodle parity slice M3, STATUS §5oj). */
class CourseForumTopic extends Model
{
    protected $fillable = [
        'course_id',
        'academic_year_id',
        'user_id',
        'title',
        'body',
        'is_pinned',
        'is_locked',
        'hidden_at',
        'hidden_by',
        'replies_count',
        'last_post_at',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'hidden_at' => 'datetime',
            'last_post_at' => 'datetime',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(CourseForumPost::class, 'topic_id');
    }
}
