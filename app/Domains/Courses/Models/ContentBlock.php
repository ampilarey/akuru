<?php

namespace App\Domains\Courses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentBlock extends Model
{
    protected $fillable = [
        'course_id',
        'course_module_id',
        'lesson_id',
        'type',
        'position',
        'title',
        'data',
        'settings',
        'is_required',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'settings' => 'array',
            'is_required' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ContentBlock $block): void {
            $lesson = Lesson::query()->find($block->lesson_id);
            if ($lesson === null) {
                return;
            }
            $block->course_id = $lesson->course_id;
            $block->course_module_id = $lesson->course_module_id;
        });
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
