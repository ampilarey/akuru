<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Enums\LessonProgressStatus;
use App\Domains\Progress\Models\StudentLessonProgress;
use Illuminate\Validation\ValidationException;

class RecordLessonProgressAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $status = LessonProgressStatus::tryFrom((string) ($data['status'] ?? LessonProgressStatus::InProgress->value));
        if ($status === null) {
            throw ValidationException::withMessages(['status' => 'Invalid progress status.']);
        }

        $revisionId = (int) ($data['lesson_revision_id'] ?? 0);
        if ($revisionId < 1) {
            throw ValidationException::withMessages([
                'lesson_revision_id' => 'Progress must store the published lesson revision.',
            ]);
        }

        $row = StudentLessonProgress::query()->firstOrNew([
            'enrollment_id' => (int) $data['enrollment_id'],
            'lesson_id' => (int) $data['lesson_id'],
        ]);

        if ($row->exists && $row->status === LessonProgressStatus::Completed && $status === LessonProgressStatus::InProgress) {
            return [
                'id' => $row->id,
                'enrollment_id' => $row->enrollment_id,
                'lesson_id' => $row->lesson_id,
                'lesson_revision_id' => $row->lesson_revision_id,
                'status' => LessonProgressStatus::Completed->value,
            ];
        }

        $row->fill([
            'course_id' => (int) $data['course_id'],
            'course_offering_id' => $data['course_offering_id'] ?? null,
            'course_module_id' => (int) $data['course_module_id'],
            'lesson_revision_id' => $revisionId,
            'student_id' => (int) $data['student_id'],
            'status' => $status,
            'started_at' => $row->started_at ?? now(),
            'completed_at' => $status === LessonProgressStatus::Completed ? ($row->completed_at ?? now()) : null,
            'score_summary' => $data['score_summary'] ?? $row->score_summary,
        ]);
        $row->save();

        return [
            'id' => $row->id,
            'enrollment_id' => $row->enrollment_id,
            'lesson_id' => $row->lesson_id,
            'lesson_revision_id' => $row->lesson_revision_id,
            'status' => $status->value,
        ];
    }
}
