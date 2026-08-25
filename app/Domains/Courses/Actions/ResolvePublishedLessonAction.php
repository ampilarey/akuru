<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Lesson;

class ResolvePublishedLessonAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(int $lessonId): ?array
    {
        $lesson = Lesson::query()->with('currentRevision')->find($lessonId);
        if ($lesson?->currentRevision === null) {
            return null;
        }

        return $lesson->currentRevision->snapshot_json;
    }
}
