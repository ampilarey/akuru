<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Enums\ActivityAttemptStatus;
use App\Domains\Progress\Enums\AssessmentAttemptStatus;
use App\Domains\Progress\Models\ActivityAttempt;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Validation\ValidationException;

class ReviewAttemptAction
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $onlyCourseIds  the reviewer's own courses (C16 slice N6): when given, an attempt from any other course is refused — an empty list refuses every attempt
     * @return array<string, mixed>
     */
    public function execute(string $kind, int $attemptId, array $data, int $reviewerId, ?array $onlyCourseIds = null): array
    {
        $score = max(0, (int) ($data['score'] ?? 0));
        $feedback = trim((string) ($data['feedback'] ?? ''));
        // Moodle parity slice M2: the rubric levels chosen, as Courses scored
        // them; null when the item has no rubric.
        $rubricScores = is_array($data['rubric_scores'] ?? null) ? $data['rubric_scores'] : null;
        $now = now();

        if ($kind === 'activity') {
            $attempt = ActivityAttempt::query()->find($attemptId);
            if ($attempt === null) {
                throw ValidationException::withMessages(['attempt' => ['Activity attempt not found.']]);
            }
            $this->assertOwnCourse((int) $attempt->course_id, $onlyCourseIds);
            $max = max(1, (int) ($attempt->max_score ?: ($data['max_score'] ?? 1)));
            $attempt->update([
                'status' => ActivityAttemptStatus::Scored,
                'score' => min($score, $max),
                'max_score' => $max,
                'feedback' => $feedback !== '' ? $feedback : null,
                'rubric_scores' => $rubricScores,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => $now,
            ]);

            return app(SaveActivityAttemptAction::class)->serialize($attempt->fresh()) + ['kind' => 'activity'];
        }

        if ($kind === 'assessment') {
            $attempt = AssessmentAttempt::query()->find($attemptId);
            if ($attempt === null) {
                throw ValidationException::withMessages(['attempt' => ['Assessment attempt not found.']]);
            }
            $this->assertOwnCourse((int) $attempt->course_id, $onlyCourseIds);
            $max = max(1, (int) ($attempt->max_score ?: ($data['max_score'] ?? 1)));
            $attempt->update([
                'status' => AssessmentAttemptStatus::Scored,
                'score' => min($score, $max),
                'max_score' => $max,
                'item_scores' => is_array($data['item_scores'] ?? null) ? $data['item_scores'] : $attempt->item_scores,
                'feedback' => $feedback !== '' ? $feedback : null,
                'rubric_scores' => $rubricScores,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => $now,
            ]);

            return app(StartAssessmentAttemptAction::class)->serialize($attempt->fresh(), includeKeys: true) + ['kind' => 'assessment'];
        }

        throw ValidationException::withMessages(['kind' => ['Review kind must be activity or assessment.']]);
    }

    /**
     * The scoped reviewer's check, on the attempt's own row rather than on
     * anything the form sent: a teacher who posts another course's attempt
     * id is told so and nothing is written.
     *
     * @param  list<int>|null  $onlyCourseIds
     */
    private function assertOwnCourse(int $courseId, ?array $onlyCourseIds): void
    {
        if ($onlyCourseIds === null) {
            return;
        }
        if (! in_array($courseId, array_map('intval', $onlyCourseIds), true)) {
            throw ValidationException::withMessages(['attempt' => ['This submission is not from one of your courses.']]);
        }
    }
}
