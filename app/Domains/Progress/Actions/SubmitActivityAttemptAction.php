<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Courses\Actions\ResolveActivityDefinitionAction;
use App\Domains\Courses\Actions\ScoreActivityAnswersAction;
use App\Domains\Progress\Enums\ActivityAttemptStatus;
use App\Domains\Progress\Models\ActivityAttempt;

class SubmitActivityAttemptAction
{
    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function execute(
        int $activityId,
        int $enrollmentId,
        int $studentId,
        int $courseId,
        array $answers,
        ?int $academicYearId = null,
    ): array {
        $definition = app(ResolveActivityDefinitionAction::class)->execute($activityId, includeAnswerKeys: true);
        $settings = is_array($definition['settings'] ?? null) ? $definition['settings'] : [];

        app(SaveActivityAttemptAction::class)->assertRetakesAvailable($activityId, $enrollmentId, $settings);

        $result = app(ScoreActivityAnswersAction::class)->execute($definition, $answers);
        $status = ActivityAttemptStatus::from($result['status']);
        $now = now();

        $attempt = ActivityAttempt::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->where('status', ActivityAttemptStatus::InProgress)
            ->orderByDesc('attempt_number')
            ->first();

        $payload = [
            'answers' => $answers,
            'status' => $status,
            'score' => $status === ActivityAttemptStatus::Scored ? $result['score'] : null,
            'max_score' => $result['max_score'],
            'submitted_at' => $now,
            'last_saved_at' => $now,
        ];

        if ($attempt === null) {
            $nextNumber = ((int) ActivityAttempt::query()
                ->where('enrollment_id', $enrollmentId)
                ->where('activity_id', $activityId)
                ->max('attempt_number')) + 1;

            $attempt = ActivityAttempt::query()->create(array_merge($payload, [
                'activity_id' => $activityId,
                'enrollment_id' => $enrollmentId,
                'student_id' => $studentId,
                'course_id' => $courseId,
                'academic_year_id' => $academicYearId,
                'attempt_number' => $nextNumber,
                'started_at' => $now,
            ]));
        } else {
            $attempt->update($payload);
        }

        $showKeys = (bool) ($settings['show_correct_answer'] ?? false) && $status === ActivityAttemptStatus::Scored;

        return [
            'attempt' => app(SaveActivityAttemptAction::class)->serialize($attempt->fresh()),
            'result' => $result,
            'activity' => app(ResolveActivityDefinitionAction::class)->execute($activityId, includeAnswerKeys: $showKeys),
        ];
    }
}
