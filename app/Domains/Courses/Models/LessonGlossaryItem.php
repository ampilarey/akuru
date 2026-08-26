<?php

namespace App\Domains\Courses\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonGlossaryItem extends Model
{
    protected $fillable = [
        'lesson_id',
        'glossary_item_id',
        'position',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(GlossaryItem::class, 'glossary_item_id');
    }
}
