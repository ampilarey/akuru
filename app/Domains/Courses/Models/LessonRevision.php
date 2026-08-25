<?php

namespace App\Domains\Courses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonRevision extends Model
{
    protected $fillable = [
        'lesson_id',
        'revision_number',
        'snapshot_json',
        'published_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_json' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
