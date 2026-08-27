<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Models\Exam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListPublishedExamResultsForStudentsAction
{
    /**
     * @param  list<int>  $studentIds
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $studentIds): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if ($ids === []) {
            return collect();
        }

        $classByStudent = DB::table('class_student')
            ->whereIn('student_id', $ids)
            ->where('status', 'active')
            ->get(['student_id', 'class_id'])
            ->groupBy('student_id');

        $classIds = $classByStudent->flatten(1)->pluck('class_id')->unique()->filter()->map(fn ($id) => (int) $id)->all();
        if ($classIds === []) {
            return collect();
        }

        $subjects = DB::table('subjects')->pluck('name', 'id');
        $exams = Exam::query()
            ->whereIn('class_id', $classIds)
            ->where('status', ExamStatus::Published)
            ->orderByDesc('exam_date')
            ->get();
        $marks = DB::table('exam_marks')
            ->whereIn('exam_id', $exams->pluck('id')->all() ?: [0])
            ->whereIn('student_id', $ids)
            ->get()
            ->groupBy(fn ($row) => $row->student_id.':'.$row->exam_id);

        $rows = collect();
        foreach ($ids as $studentId) {
            $studentClassIds = collect($classByStudent->get($studentId, collect()))
                ->pluck('class_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            foreach ($exams as $exam) {
                if (! in_array((int) $exam->class_id, $studentClassIds, true)) {
                    continue;
                }
                $mark = $marks->get($studentId.':'.$exam->id)?->first();
                $rows->push([
                    'student_id' => $studentId,
                    'id' => $exam->id,
                    'name' => $exam->name,
                    'exam_date' => $exam->exam_date?->toDateString(),
                    'subject' => $subjects[$exam->subject_id] ?? null,
                    'max_marks' => $exam->max_marks === null ? null : (float) $exam->max_marks,
                    'marks' => $mark?->marks === null ? null : (float) $mark->marks,
                    'is_absent' => (bool) ($mark?->is_absent ?? false),
                    'is_exempt' => (bool) ($mark?->is_exempt ?? false),
                ]);
            }
        }

        return $rows->values();
    }
}
