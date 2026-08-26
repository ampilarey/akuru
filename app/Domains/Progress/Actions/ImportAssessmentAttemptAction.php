<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Enums\AssessmentAttemptStatus;
use App\Domains\Progress\Models\AssessmentAttempt;

class ImportAssessmentAttemptAction
{
    /**
     * Idempotent write of a historical attempt (legacy quiz/assignment).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $quizAttemptId = isset($data['legacy_quiz_attempt_id']) ? (int) $data['legacy_quiz_attempt_id'] : null;
        $assignmentSubmissionId = isset($data['legacy_assignment_submission_id'])
            ? (int) $data['legacy_assignment_submission_id']
            : null;

        $existing = null;
        if ($quizAttemptId) {
            $existing = AssessmentAttempt::query()
                ->where('legacy_quiz_attempt_id', $quizAttemptId)
                ->first();
        }
        if ($existing === null && $assignmentSubmissionId) {
            $existing = AssessmentAttempt::query()
                ->where('legacy_assignment_submission_id', $assignmentSubmissionId)
                ->first();
        }
        if ($existing !== null) {
            return app(StartAssessmentAttemptAction::class)->serialize($existing, includeKeys: true) + ['imported' => false];
        }

        $status = AssessmentAttemptStatus::tryFrom((string) ($data['status'] ?? 'scored'))
            ?? AssessmentAttemptStatus::Scored;

        $attempt = AssessmentAttempt::query()->create([
            'assessment_id' => (int) $data['assessment_id'],
            'enrollment_id' => $data['enrollment_id'] ?? null,
            'student_id' => (int) $data['student_id'],
            'course_id' => $data['course_id'] ?? null,
            'classroom_id' => $data['classroom_id'] ?? null,
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'attempt_number' => (int) ($data['attempt_number'] ?? 1),
            'status' => $status,
            'answers' => is_array($data['answers'] ?? null) ? $data['answers'] : [],
            'snapshots' => is_array($data['snapshots'] ?? null) ? $data['snapshots'] : [],
            'score' => $data['score'] ?? null,
            'max_score' => $data['max_score'] ?? null,
            'started_at' => $data['started_at'] ?? null,
            'last_saved_at' => $data['last_saved_at'] ?? $data['submitted_at'] ?? null,
            'submitted_at' => $data['submitted_at'] ?? null,
            'feedback' => $data['feedback'] ?? null,
            'item_scores' => $data['item_scores'] ?? null,
            'reviewed_by' => $data['reviewed_by'] ?? null,
            'reviewed_at' => $data['reviewed_at'] ?? null,
            'legacy_quiz_attempt_id' => $quizAttemptId,
            'legacy_assignment_submission_id' => $assignmentSubmissionId,
        ]);

        return app(StartAssessmentAttemptAction::class)->serialize($attempt, includeKeys: true) + ['imported' => true];
    }
}
