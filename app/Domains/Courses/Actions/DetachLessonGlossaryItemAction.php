<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Lesson;
use App\Domains\Courses\Models\LessonGlossaryItem;

class DetachLessonGlossaryItemAction
{
    public function execute(Lesson $lesson, int $glossaryItemId): void
    {
        LessonGlossaryItem::query()
            ->where('lesson_id', $lesson->id)
            ->where('glossary_item_id', $glossaryItemId)
            ->delete();
    }
}
