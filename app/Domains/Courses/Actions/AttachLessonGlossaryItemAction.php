<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\GlossaryItem;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Courses\Models\LessonGlossaryItem;
use Illuminate\Validation\ValidationException;

class AttachLessonGlossaryItemAction
{
    public function execute(Lesson $lesson, int $glossaryItemId, bool $isRequired = false): LessonGlossaryItem
    {
        GlossaryItem::query()->findOrFail($glossaryItemId);

        $existing = LessonGlossaryItem::query()
            ->where('lesson_id', $lesson->id)
            ->where('glossary_item_id', $glossaryItemId)
            ->first();
        if ($existing !== null) {
            throw ValidationException::withMessages([
                'glossary_item_id' => 'That term is already attached to this lesson.',
            ]);
        }

        $position = (int) LessonGlossaryItem::query()
            ->where('lesson_id', $lesson->id)
            ->max('position') + 1;

        return LessonGlossaryItem::query()->create([
            'lesson_id' => $lesson->id,
            'glossary_item_id' => $glossaryItemId,
            'position' => $position,
            'is_required' => $isRequired,
        ]);
    }
}
