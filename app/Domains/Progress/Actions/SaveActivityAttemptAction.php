<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Enums\ActivityAttemptStatus;
use App\Domains\Progress\Models\ActivityAttempt;
use Illuminate\Validation\ValidationException;

class SaveActivityAttemptAction
{
    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function execute(
        int $activityId,
        int $enrollmentId,
        int $studentId,
        int $courseId,
        array $answers,
        array $settings = [],
        ?int $academicYearId = null,
    ): array {
        $this->assertRetakesAvailable($activityId, $enrollmentId, $settings);

        $attempt = ActivityAttempt::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->where('status', ActivityAttemptStatus::InProgress)
            ->orderByDesc('attempt_number')
            ->first();

        $now = now();

        if ($attempt === null) {
            $attempt = ActivityAttempt::query()->create([
                'activity_id' => $activityId,
                'enrollment_id' => $enrollmentId,
                'student_id' => $studentId,
                'course_id' => $courseId,
                'academic_year_id' => $academicYearId,
                'attempt_number' => $this->nextNumber($activityId, $enrollmentId),
                'status' => ActivityAttemptStatus::InProgress,
                'answers' => $answers,
                'started_at' => $now,
                'last_saved_at' => $now,
            ]);
        } else {
            $attempt->update([
                'answers' => $answers,
                'last_saved_at' => $now,
            ]);
        }

        return $this->serialize($attempt->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ActivityAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'activity_id' => $attempt->activity_id,
            'enrollment_id' => $attempt->enrollment_id,
            'student_id' => $attempt->student_id ? (int) $attempt->student_id : null,
            'course_id' => $attempt->course_id ? (int) $attempt->course_id : null,
            'academic_year_id' => $attempt->academic_year_id ? (int) $attempt->academic_year_id : null,
            'attempt_number' => $attempt->attempt_number,
            'status' => $attempt->status->value,
            'answers' => $attempt->answers,
            'score' => $attempt->score,
            'max_score' => $attempt->max_score,
            'started_at' => optional($attempt->started_at)?->toIso8601String(),
            'last_saved_at' => optional($attempt->last_saved_at)?->toIso8601String(),
            'submitted_at' => optional($attempt->submitted_at)?->toIso8601String(),
            'feedback' => $attempt->feedback,
            'reviewed_at' => optional($attempt->reviewed_at)?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function assertRetakesAvailable(int $activityId, int $enrollmentId, array $settings): void
    {
        $submittedCount = ActivityAttempt::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->whereIn('status', [ActivityAttemptStatus::Submitted, ActivityAttemptStatus::Scored])
            ->count();

        $retakesAllowed = (bool) ($settings['retakes_allowed'] ?? true);
        $retakeLimit = isset($settings['retake_limit']) && $settings['retake_limit'] !== ''
            ? (int) $settings['retake_limit']
            : null;

        if (! $retakesAllowed && $submittedCount >= 1) {
            throw ValidationException::withMessages([
                'attempt' => ['Retakes are not allowed for this activity.'],
            ]);
        }
        if ($retakeLimit !== null && $submittedCount >= $retakeLimit) {
            throw ValidationException::withMessages([
                'attempt' => ['Retake limit reached.'],
            ]);
        }
    }

    private function nextNumber(int $activityId, int $enrollmentId): int
    {
        return ((int) ActivityAttempt::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->max('attempt_number')) + 1;
    }
}
