<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Courses\Actions\BuildAssessmentSnapshotsAction;
use App\Domains\Courses\Actions\ResolveAssessmentSettingsAction;
use App\Domains\Progress\Enums\AssessmentAttemptStatus;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Validation\ValidationException;

class StartAssessmentAttemptAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(
        int $assessmentId,
        int $enrollmentId,
        int $studentId,
        int $courseId,
        ?int $academicYearId = null,
    ): array {
        $existing = AssessmentAttempt::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('assessment_id', $assessmentId)
            ->where('status', AssessmentAttemptStatus::InProgress)
            ->orderByDesc('attempt_number')
            ->first();

        if ($existing !== null) {
            return $this->serialize($existing);
        }

        $settings = app(ResolveAssessmentSettingsAction::class)->execute($assessmentId);
        $this->assertRetakesAvailable($assessmentId, $enrollmentId, $settings['retake_limit'] ?? null);

        $snapshots = app(BuildAssessmentSnapshotsAction::class)->execute(
            $assessmentId,
            (bool) $settings['randomize_questions'],
        );

        $attempt = AssessmentAttempt::query()->create([
            'assessment_id' => $assessmentId,
            'enrollment_id' => $enrollmentId,
            'student_id' => $studentId,
            'course_id' => $courseId,
            'academic_year_id' => $academicYearId,
            'attempt_number' => $this->nextNumber($assessmentId, $enrollmentId),
            'status' => AssessmentAttemptStatus::InProgress,
            'answers' => [],
            'snapshots' => $snapshots,
            'started_at' => now(),
            'last_saved_at' => now(),
        ]);

        return $this->serialize($attempt);
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(AssessmentAttempt $attempt, bool $includeKeys = false): array
    {
        $snapshots = $attempt->snapshots ?? [];
        if (! $includeKeys) {
            $snapshots = array_map(function (array $snapshot): array {
                unset($snapshot['correct_answer'], $snapshot['acceptable_answers'], $snapshot['explanation']);

                return $snapshot;
            }, $snapshots);
        }

        return [
            'id' => $attempt->id,
            'assessment_id' => $attempt->assessment_id,
            'enrollment_id' => $attempt->enrollment_id,
            'attempt_number' => $attempt->attempt_number,
            'status' => $attempt->status->value,
            'answers' => $attempt->answers,
            'snapshots' => $snapshots,
            'score' => $attempt->score,
            'max_score' => $attempt->max_score,
            'started_at' => optional($attempt->started_at)?->toIso8601String(),
            'last_saved_at' => optional($attempt->last_saved_at)?->toIso8601String(),
            'submitted_at' => optional($attempt->submitted_at)?->toIso8601String(),
            'feedback' => $attempt->feedback,
            'item_scores' => $attempt->item_scores,
            'reviewed_at' => optional($attempt->reviewed_at)?->toIso8601String(),
        ];
    }

    public function assertRetakesAvailable(int $assessmentId, int $enrollmentId, mixed $retakeLimit): void
    {
        $submitted = AssessmentAttempt::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('assessment_id', $assessmentId)
            ->whereIn('status', [AssessmentAttemptStatus::Submitted, AssessmentAttemptStatus::Scored])
            ->count();

        if ($retakeLimit !== null && $submitted >= (int) $retakeLimit) {
            throw ValidationException::withMessages([
                'attempt' => ['Retake limit reached.'],
            ]);
        }
    }

    private function nextNumber(int $assessmentId, int $enrollmentId): int
    {
        return ((int) AssessmentAttempt::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('assessment_id', $assessmentId)
            ->max('attempt_number')) + 1;
    }
}
