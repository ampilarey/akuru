<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Models\StudentLessonProgress;

class ListLessonProgressAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $enrollmentId): array
    {
        return StudentLessonProgress::query()
            ->where('enrollment_id', $enrollmentId)
            ->orderBy('id')
            ->get()
            ->map(fn (StudentLessonProgress $row) => [
                'id' => $row->id,
                'lesson_id' => $row->lesson_id,
                'lesson_revision_id' => $row->lesson_revision_id,
                'status' => $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status,
                'started_at' => $row->started_at?->toIso8601String(),
                'completed_at' => $row->completed_at?->toIso8601String(),
            ])
            ->all();
    }
}
