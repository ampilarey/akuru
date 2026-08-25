<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Lesson;
use App\Domains\Courses\Models\LessonRevision;

class ResolvePublishedLessonAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(int $lessonId, ?int $revisionId = null): ?array
    {
        if ($revisionId !== null) {
            $revision = LessonRevision::query()
                ->where('lesson_id', $lessonId)
                ->where('id', $revisionId)
                ->first();

            return $revision?->snapshot_json;
        }

        $lesson = Lesson::query()->with('currentRevision')->find($lessonId);
        if ($lesson?->currentRevision === null) {
            return null;
        }

        return $lesson->currentRevision->snapshot_json;
    }
}
