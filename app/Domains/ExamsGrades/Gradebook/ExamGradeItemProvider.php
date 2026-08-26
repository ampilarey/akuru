<?php

namespace App\Domains\ExamsGrades\Gradebook;

use App\Domains\ExamsGrades\Contracts\GradeItemProvider;
use App\Domains\ExamsGrades\DTOs\GradeItem;
use App\Domains\ExamsGrades\Models\Exam;
use App\Domains\ExamsGrades\Models\ExamMark;

class ExamGradeItemProvider implements GradeItemProvider
{
    /**
     * @param  list<int>  $studentIds
     * @return list<GradeItem>
     */
    public function items(int $classId, int $subjectId, int $termId, array $studentIds): array
    {
        $exams = Exam::query()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->orderBy('exam_date')
            ->orderBy('id')
            ->get();

        if ($exams->isEmpty()) {
            return [];
        }

        $marks = ExamMark::query()
            ->whereIn('exam_id', $exams->pluck('id')->all())
            ->get()
            ->groupBy('exam_id');

        return $exams
            ->map(function (Exam $exam) use ($marks): GradeItem {
                $max = $exam->max_marks !== null ? (float) $exam->max_marks : null;
                $byStudent = [];
                foreach ($marks[$exam->id] ?? [] as $mark) {
                    $byStudent[(int) $mark->student_id] = [
                        'score' => $mark->marks !== null ? (float) $mark->marks : null,
                        'max_score' => $max,
                        'status' => $mark->is_absent
                            ? 'absent'
                            : ($mark->is_exempt ? 'exempt' : ($mark->marks !== null ? 'scored' : null)),
                        'is_absent' => (bool) $mark->is_absent,
                        'is_exempt' => (bool) $mark->is_exempt,
                    ];
                }

                return new GradeItem(
                    key: 'exam:'.$exam->id,
                    label: $exam->name,
                    source: 'exam',
                    maxScore: $max,
                    resultsByStudent: $byStudent,
                );
            })
            ->all();
    }
}
