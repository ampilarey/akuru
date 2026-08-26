<?php

namespace App\Domains\Courses\Gradebook;

use App\Domains\Courses\Enums\AssessmentStatus;
use App\Domains\Courses\Models\Assessment;
use App\Domains\ExamsGrades\Contracts\GradeItemProvider;
use App\Domains\ExamsGrades\DTOs\GradeItem;
use App\Domains\Progress\Actions\ListAssessmentScoresAction;

class ClassroomAssessmentGradeItemProvider implements GradeItemProvider
{
    /**
     * @param  list<int>  $studentIds
     * @return list<GradeItem>
     */
    public function items(int $classId, int $subjectId, int $termId, array $studentIds): array
    {
        $assessments = Assessment::query()
            ->where('classroom_id', $classId)
            ->where('status', AssessmentStatus::Published)
            ->where(function ($query) use ($termId): void {
                $query->whereNull('term_id')->orWhere('term_id', $termId);
            })
            ->orderBy('id')
            ->get()
            ->filter(fn (Assessment $assessment): bool => $this->matchesSubject($assessment, $subjectId))
            ->values();

        if ($assessments->isEmpty()) {
            return [];
        }

        $scores = app(ListAssessmentScoresAction::class)->execute(
            $assessments->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $studentIds,
        );

        return $assessments
            ->map(fn (Assessment $assessment): GradeItem => $this->toItem($assessment, $scores[$assessment->id] ?? []))
            ->all();
    }

    private function matchesSubject(Assessment $assessment, int $subjectId): bool
    {
        $schoolSubjectId = $assessment->settings['school_subject_id'] ?? null;
        if ($schoolSubjectId === null || $schoolSubjectId === '') {
            return true;
        }

        return (int) $schoolSubjectId === $subjectId;
    }

    /**
     * @param  array<int, array{score: float|null, max_score: float|null, status: string|null, is_absent: bool, is_exempt: bool}>  $results
     */
    private function toItem(Assessment $assessment, array $results): GradeItem
    {
        $max = $assessment->max_score !== null ? (float) $assessment->max_score : null;
        foreach ($results as $studentId => $row) {
            if ($row['max_score'] === null && $max !== null) {
                $results[$studentId]['max_score'] = $max;
            }
        }

        return new GradeItem(
            key: 'assessment:'.$assessment->id,
            label: $assessment->title,
            source: 'assessment',
            maxScore: $max,
            resultsByStudent: $results,
        );
    }
}
