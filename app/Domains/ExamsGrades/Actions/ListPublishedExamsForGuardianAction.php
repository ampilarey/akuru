<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Models\Exam;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListPublishedExamsForGuardianAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $guardianUserId, ?int $studentId = null): Collection
    {
        $children = app(ListGuardianChildrenAction::class)->executeForGuardianUserId($guardianUserId);
        $childIds = $children->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($studentId !== null && ! in_array($studentId, $childIds, true)) {
            return collect();
        }

        $ids = $studentId !== null ? [$studentId] : $childIds;
        if ($ids === []) {
            return collect();
        }

        $classIds = DB::table('class_student')
            ->whereIn('student_id', $ids)
            ->where('status', 'active')
            ->pluck('class_id')
            ->unique()
            ->all();

        if ($classIds === []) {
            return collect();
        }

        $subjects = DB::table('subjects')->whereIn('id', function ($query) use ($classIds): void {
            $query->select('subject_id')->from('exams')->whereIn('class_id', $classIds);
        })->pluck('name', 'id');

        return Exam::query()
            ->whereIn('class_id', $classIds)
            ->where('status', ExamStatus::Published)
            ->orderBy('exam_date')
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'name' => $exam->name,
                'exam_date' => $exam->exam_date?->toDateString(),
                'subject' => $subjects[$exam->subject_id] ?? null,
                'max_marks' => $exam->max_marks,
                'published_at' => $exam->published_at?->toDateTimeString(),
            ]);
    }
}
