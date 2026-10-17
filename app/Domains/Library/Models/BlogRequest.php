<?php

namespace App\Domains\Library\Models;

use App\Domains\Library\Enums\BlogRequestStatus;
use Illuminate\Database\Eloquent\Model;

/** BLOG_PLAN BL1: a student's request to start a blog (STATUS §5se). */
class BlogRequest extends Model
{
    protected $fillable = [
        'user_id',
        'student_id',
        'pen_name',
        'blog_title',
        'about',
        'under_18',
        'status',
        'guardian_user_id',
        'guardian_decided_at',
        'show_photo',
        'show_school',
        'decided_by',
        'decided_at',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'under_18' => 'boolean',
            'status' => BlogRequestStatus::class,
            'guardian_decided_at' => 'datetime',
            'show_photo' => 'boolean',
            'show_school' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }
}
