<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Contracts\GradeItemContract;
use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Gradebook\GradeItemRegistry;
use App\Domains\ExamsGrades\Models\Competency;
use App\Domains\ExamsGrades\Models\CompetencyAssessment;
use App\Domains\ExamsGrades\Models\Exam;
use App\Domains\ExamsGrades\Models\ExamMark;
use App\Domains\ExamsGrades\Models\TermGrade;
use Illuminate\Support\Facades\DB;

class ListGradebookAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $classId, int $subjectId, int $termId): array
    {
        $exams = Exam::query()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->orderBy('exam_date')
            ->get();

        $term = DB::table('terms')->where('id', $termId)->first();
        $yearId = (int) ($term->academic_year_id ?? 0);
        $scheme = $yearId
            ? app(ResolveWeightSchemeAction::class)->execute($yearId, $classId, $subjectId)
            : null;
        $hasWeights = $scheme !== null && collect($scheme->weights ?? [])
            ->contains(fn ($weight) => (float) $weight > 0);
        $asOf = $term->end_date ?? now()->toDateString();
        $studentIds = DB::table('class_student')
            ->where('class_id', $classId)
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('enrolled_at')->orWhereDate('enrolled_at', '<=', $asOf);
            })
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('left_at')->orWhereDate('left_at', '>=', $asOf);
            })
            ->pluck('student_id');

        $students = DB::table('students')
            ->whereIn('id', $studentIds)
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        $rosterIds = $students->map(fn ($student): int => (int) $student->id)->all();
        $gradeItems = app(GradeItemRegistry::class)->items($classId, $subjectId, $termId, $rosterIds);
        $itemResults = [];
        foreach ($gradeItems as $item) {
            $itemResults[$item->key()] = $item->results($rosterIds);
        }

        $marks = ExamMark::query()->whereIn('exam_id', $exams->pluck('id')->all() ?: [0])->get()->groupBy('student_id');
        $grades = TermGrade::query()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->get()
            ->keyBy('student_id');

        $competencies = Competency::query()->where('subject_id', $subjectId)->orderBy('sort_order')->get();
        $assessments = CompetencyAssessment::query()
            ->whereIn('competency_id', $competencies->pluck('id')->all() ?: [0])
            ->where('term_id', $termId)
            ->get();

        $rows = $students->map(function ($student) use ($exams, $marks, $grades, $competencies, $assessments, $gradeItems, $itemResults) {
            $studentMarks = ($marks[$student->id] ?? collect())->keyBy('exam_id');
            $grade = $grades[$student->id] ?? null;
            $studentId = (int) $student->id;

            return [
                'student_id' => $studentId,
                'name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                'marks' => $exams->mapWithKeys(function (Exam $exam) use ($studentMarks) {
                    $mark = $studentMarks[$exam->id] ?? null;

                    return [$exam->id => [
                        'marks' => $mark?->marks,
                        'is_absent' => (bool) ($mark?->is_absent ?? false),
                        'is_exempt' => (bool) ($mark?->is_exempt ?? false),
                    ]];
                }),
                'items' => collect($gradeItems)->mapWithKeys(function (GradeItemContract $item) use ($itemResults, $studentId) {
                    return [$item->key() => $itemResults[$item->key()][$studentId] ?? null];
                })->all(),
                'term' => $grade === null ? null : [
                    'weighted_percent' => $grade->weighted_percent,
                    'grade' => $grade->grade,
                    'grade_point' => $grade->grade_point,
                    'rank' => $grade->rank,
                    'components' => $grade->components,
                ],
                'competencies' => $competencies->mapWithKeys(function (Competency $competency) use ($student, $assessments) {
                    $row = $assessments->first(fn (CompetencyAssessment $assessment) => $assessment->student_id === (int) $student->id
                        && $assessment->competency_id === $competency->id);

                    return [$competency->id => $row?->level];
                }),
            ];
        });

        return [
            'exams' => $exams->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'name' => $exam->name,
                'status' => $exam->status->value,
                'published' => $exam->status === ExamStatus::Published,
            ])->values(),
            'grade_items' => collect($gradeItems)->map(fn (GradeItemContract $item) => [
                'key' => $item->key(),
                'label' => $item->label(),
                'source' => $item->source(),
                'max_score' => $item->maxScore(),
            ])->values()->all(),
            'competencies' => $competencies->map(fn (Competency $competency) => [
                'id' => $competency->id,
                'name' => $competency->name,
            ])->values(),
            'rows' => $rows->values(),
            'missing_weights' => ! $hasWeights,
        ];
    }
}
