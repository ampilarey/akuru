<?php

namespace App\Domains\Academics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegisterUnlock extends Model
{
    protected $fillable = [
        'lesson_log_id',
        'unlocked_by',
        'reason',
        'unlocked_at',
    ];

    protected function casts(): array
    {
        return [
            'unlocked_at' => 'datetime',
        ];
    }

    public function lessonLog(): BelongsTo
    {
        return $this->belongsTo(LessonLog::class);
    }
}
