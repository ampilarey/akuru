<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Enums\AssessmentAttemptStatus;
use App\Domains\Progress\Models\AssessmentAttempt;

class ListAssessmentScoresAction
{
    /**
     * Latest attempt per student per assessment, preferring scored over submitted over in-progress.
     *
     * @param  list<int>  $assessmentIds
     * @param  list<int>  $studentIds
     * @return array<int, array<int, array{score: float|null, max_score: float|null, status: string|null, is_absent: bool, is_exempt: bool}>>
     */
    public function execute(array $assessmentIds, array $studentIds): array
    {
        $assessmentIds = array_values(array_unique(array_filter(array_map('intval', $assessmentIds))));
        $studentIds = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if ($assessmentIds === [] || $studentIds === []) {
            return [];
        }

        $attempts = AssessmentAttempt::query()
            ->whereIn('assessment_id', $assessmentIds)
            ->whereIn('student_id', $studentIds)
            ->get();

        /** @var array<int, array<int, AssessmentAttempt>> $best */
        $best = [];
        foreach ($attempts as $attempt) {
            $assessmentId = (int) $attempt->assessment_id;
            $studentId = (int) $attempt->student_id;
            $current = $best[$assessmentId][$studentId] ?? null;
            if ($current === null || $this->isBetter($attempt, $current)) {
                $best[$assessmentId][$studentId] = $attempt;
            }
        }

        $out = [];
        foreach ($best as $assessmentId => $byStudent) {
            foreach ($byStudent as $studentId => $attempt) {
                $out[$assessmentId][$studentId] = [
                    'score' => $attempt->score !== null ? (float) $attempt->score : null,
                    'max_score' => $attempt->max_score !== null ? (float) $attempt->max_score : null,
                    'status' => $attempt->status->value,
                    'is_absent' => false,
                    'is_exempt' => false,
                ];
            }
        }

        return $out;
    }

    private function isBetter(AssessmentAttempt $candidate, AssessmentAttempt $current): bool
    {
        $candidateRank = $this->rank($candidate);
        $currentRank = $this->rank($current);
        if ($candidateRank !== $currentRank) {
            return $candidateRank > $currentRank;
        }
        if ($candidate->attempt_number !== $current->attempt_number) {
            return $candidate->attempt_number > $current->attempt_number;
        }

        return $candidate->id > $current->id;
    }

    private function rank(AssessmentAttempt $attempt): int
    {
        return match ($attempt->status) {
            AssessmentAttemptStatus::Scored => 3,
            AssessmentAttemptStatus::Submitted => 2,
            AssessmentAttemptStatus::InProgress => 1,
        };
    }
}
