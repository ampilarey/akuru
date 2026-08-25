<?php

namespace App\Domains\ExamsGrades\Actions;

use App\Domains\ExamsGrades\Enums\ExamStatus;
use App\Domains\ExamsGrades\Models\Exam;
use App\Domains\ExamsGrades\Models\ExamMark;
use App\Domains\ExamsGrades\Models\GradeScale;
use App\Domains\ExamsGrades\Models\TermGrade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ComputeTermGradesAction
{
    /**
     * @return Collection<int, TermGrade>
     */
    public function execute(int $classId, int $subjectId, int $termId): Collection
    {
        $term = DB::table('terms')->where('id', $termId)->first();
        if ($term === null) {
            return collect();
        }

        $yearId = (int) $term->academic_year_id;
        $settings = app(ResolveExamSettingsAction::class)->execute();
        $scheme = app(ResolveWeightSchemeAction::class)->execute($yearId, $classId, $subjectId);
        $scale = GradeScale::query()->where('is_default', true)->first();
        $exams = Exam::query()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->where('status', ExamStatus::Published)
            ->orderBy('id')
            ->get();

        $shares = $this->shares($exams, $scheme?->weights ?? []);
        $marks = ExamMark::query()->whereIn('exam_id', $exams->pluck('id')->all() ?: [0])->get()->groupBy('student_id');

        $asOf = $term->end_date ?? now()->toDateString();
        $studentIds = DB::table('class_student')
            ->where('class_id', $classId)
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('enrolled_at')->orWhereDate('enrolled_at', '<=', $asOf);
            })
            ->where(function ($query) use ($asOf): void {
                $query->whereNull('left_at')->orWhereDate('left_at', '>=', $asOf);
            })
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $rows = [];
        foreach ($studentIds as $studentId) {
            $studentMarks = ($marks[$studentId] ?? collect())->keyBy('exam_id');
            $computed = $this->student($exams, $shares, $studentMarks, $settings['exclude_absent']);
            $mapped = $computed['percent'] === null
                ? ['grade' => null, 'grade_point' => null]
                : app(MapPercentToGradeAction::class)->execute($computed['percent'], $scale);

            $rows[] = TermGrade::query()->updateOrCreate(
                [
                    'student_id' => $studentId,
                    'subject_id' => $subjectId,
                    'term_id' => $termId,
                ],
                [
                    'class_id' => $classId,
                    'academic_year_id' => $yearId,
                    'weighted_percent' => $computed['percent'],
                    'grade' => $mapped['grade'],
                    'grade_point' => $mapped['grade_point'],
                    'rank' => null,
                    'components' => $computed['components'],
                    'computed_at' => now(),
                ],
            );
        }

        $this->ranks($classId, $subjectId, $termId, $settings);

        return TermGrade::query()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->orderBy('rank')
            ->orderBy('student_id')
            ->get();
    }

    /**
     * @param  Collection<int, Exam>  $exams
     * @param  array<string, mixed>  $weights
     * @return array<int, float>
     */
    private function shares(Collection $exams, array $weights): array
    {
        $byType = $exams->groupBy('exam_type_id');
        $shares = [];

        foreach ($byType as $typeId => $group) {
            $typeWeight = (float) ($weights[(string) $typeId] ?? $weights[$typeId] ?? 0);
            $overridden = $group->filter(fn (Exam $exam) => $exam->weight_override !== null);
            $plain = $group->filter(fn (Exam $exam) => $exam->weight_override === null);

            foreach ($overridden as $exam) {
                $shares[$exam->id] = (float) $exam->weight_override;
            }

            $remaining = $typeWeight - $overridden->sum(fn (Exam $exam) => (float) $exam->weight_override);
            $split = $plain->count() > 0 ? $remaining / $plain->count() : 0;
            foreach ($plain as $exam) {
                $shares[$exam->id] = $split;
            }
        }

        return $shares;
    }

    /**
     * @param  Collection<int, Exam>  $exams
     * @param  array<int, float>  $shares
     * @param  Collection<int, ExamMark>  $studentMarks
     * @return array{percent: float|null, components: list<array<string, mixed>>}
     */
    private function student(Collection $exams, array $shares, Collection $studentMarks, bool $excludeAbsent): array
    {
        $components = [];
        $weighted = 0.0;
        $usedShare = 0.0;

        foreach ($exams as $exam) {
            $mark = $studentMarks[$exam->id] ?? null;
            $share = $shares[$exam->id] ?? 0.0;
            $absent = (bool) ($mark?->is_absent ?? false);
            $exempt = (bool) ($mark?->is_exempt ?? false);
            $excluded = $exempt || ($absent && $excludeAbsent);
            $raw = $mark?->marks;
            $percent = null;
            if (! $excluded) {
                if ($absent) {
                    $percent = 0.0;
                } elseif ($raw !== null) {
                    $max = max(0.0001, (float) $exam->max_marks);
                    $percent = ((float) $raw / $max) * 100;
                }
            }

            if ($percent !== null) {
                $weighted += $percent * ($share / 100);
                $usedShare += $share;
            }

            $components[] = [
                'exam_id' => $exam->id,
                'exam_type_id' => $exam->exam_type_id,
                'name' => $exam->name,
                'share' => round($share, 2),
                'max_marks' => (float) $exam->max_marks,
                'marks' => $raw !== null ? (float) $raw : null,
                'is_absent' => $absent,
                'is_exempt' => $exempt,
                'excluded' => $excluded,
                'percent' => $percent !== null ? round($percent, 2) : null,
                'weighted' => $percent !== null ? round($percent * ($share / 100), 2) : null,
            ];
        }

        if ($usedShare <= 0) {
            return ['percent' => null, 'components' => $components];
        }

        return ['percent' => round($weighted, 2), 'components' => $components];
    }

    private function ranks(int $classId, int $subjectId, int $termId, array $settings): void
    {
        if (! ($settings['compute_rank'] ?? true)) {
            TermGrade::query()
                ->where('class_id', $classId)
                ->where('subject_id', $subjectId)
                ->where('term_id', $termId)
                ->update(['rank' => null]);

            return;
        }

        $rows = TermGrade::query()
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->whereNotNull('weighted_percent')
            ->orderByDesc('weighted_percent')
            ->orderBy('student_id')
            ->get();

        $rank = 0;
        $seen = 0;
        $previous = null;
        foreach ($rows as $row) {
            $seen++;
            $current = (string) $row->weighted_percent;
            if ($current !== $previous) {
                $rank = $seen;
                $previous = $current;
            }
            $row->rank = $rank;
            $row->save();
        }
    }
}
