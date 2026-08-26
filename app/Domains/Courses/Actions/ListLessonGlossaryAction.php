<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\LessonGlossaryItem;

class ListLessonGlossaryAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $lessonId): array
    {
        return LessonGlossaryItem::query()
            ->where('lesson_id', $lessonId)
            ->with('item')
            ->orderBy('position')
            ->get()
            ->filter(fn (LessonGlossaryItem $row) => $row->item !== null)
            ->map(fn (LessonGlossaryItem $row) => $row->item->toPayload($row->position, $row->is_required))
            ->values()
            ->all();
    }
}
