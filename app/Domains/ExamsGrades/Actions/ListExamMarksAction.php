<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Models\Exam;
use App\Domains\ExamsGrades\Models\ExamMark;
use Illuminate\Support\Collection;

class ListExamMarksAction
{
    /**
     * @return array{rows: Collection<int, array<string, mixed>>, progress: array{total: int, entered: int, blank: int, absent: int, exempt: int}}
     */
    public function execute(Exam $exam): array
    {
        $roster = app(ListExamRosterAction::class)->execute($exam);
        $marks = ExamMark::query()->where('exam_id', $exam->id)->get()->keyBy('student_id');

        $rows = $roster->map(function (array $student) use ($marks, $exam) {
            $mark = $marks[$student['student_id']] ?? null;
            $value = $mark?->marks;
            $anomaly = $value !== null && (float) $value > (float) $exam->max_marks;

            return [
                'student_id' => $student['student_id'],
                'name' => $student['name'],
                'student_number' => $student['student_number'],
                'marks' => $value,
                'is_absent' => (bool) ($mark?->is_absent ?? false),
                'is_exempt' => (bool) ($mark?->is_exempt ?? false),
                'remarks' => $mark?->remarks,
                'entered' => $mark !== null && ($value !== null || $mark->is_absent || $mark->is_exempt),
                'anomaly' => $anomaly,
            ];
        });

        $entered = $rows->where('entered', true)->count();

        return [
            'rows' => $rows,
            'progress' => [
                'total' => $rows->count(),
                'entered' => $entered,
                'blank' => $rows->count() - $entered,
                'absent' => $rows->where('is_absent', true)->count(),
                'exempt' => $rows->where('is_exempt', true)->count(),
            ],
        ];
    }
}
