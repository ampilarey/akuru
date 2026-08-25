<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Lesson;

class ListPublishedLessonPinsAction
{
    /**
     * @return array<int, int> lesson_id => revision_id
     */
    public function execute(int $courseId): array
    {
        return Lesson::query()
            ->where('course_id', $courseId)
            ->whereNotNull('current_revision_id')
            ->pluck('current_revision_id', 'id')
            ->mapWithKeys(fn ($revisionId, $lessonId) => [(int) $lessonId => (int) $revisionId])
            ->all();
    }
}
