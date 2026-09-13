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
                // SPEC §25's `score_summary`, which was written to accept a
                // value nothing sent and, once something did, would have been
                // a column nothing read. The whole point of freezing it on the
                // row is that a student can look back at what a lesson was
                // completed with.
                'score_summary' => $row->score_summary,
            ])
            ->all();
    }
}
