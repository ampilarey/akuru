<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Courses\Actions\ListUnansweredRequiredQuestionsAction;
use App\Domains\Courses\Actions\ResolveAssessmentSettingsAction;
use App\Domains\Courses\Actions\ScoreAssessmentSnapshotsAction;
use App\Domains\Progress\Enums\AssessmentAttemptStatus;
use Illuminate\Validation\ValidationException;

class SubmitAssessmentAttemptAction
{
    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function execute(int $assessmentId, ?int $enrollmentId, array $answers, ?int $studentId = null): array
    {
        $settings = app(ResolveAssessmentSettingsAction::class)->execute($assessmentId);
        app(StartAssessmentAttemptAction::class)->assertRetakesAvailable(
            $assessmentId,
            $enrollmentId,
            $settings['retake_limit'] ?? null,
            $studentId,
        );

        $attempt = app(StartAssessmentAttemptAction::class)
            ->scopedQuery($assessmentId, $enrollmentId, $studentId)
            ->where('status', AssessmentAttemptStatus::InProgress)
            ->orderByDesc('attempt_number')
            ->first();

        if ($attempt === null) {
            throw ValidationException::withMessages([
                'attempt' => ['Start the assessment before submitting.'],
            ]);
        }

        // SPEC §31: the cut-off is computed here, from `started_at` and the
        // configured limit, never from anything the browser sent.
        $deadline = app(ResolveAssessmentDeadlineAction::class)->execute($attempt, $settings);

        // Late answers do not count — but nothing the student saved is thrown
        // away. Autosave (`SaveAssessmentAttemptAction`) has been writing
        // `answers` throughout, so the attempt is scored on what was in hand
        // when time ran out. Refusing outright would punish a slow connection
        // exactly as hard as cheating.
        $scoredAnswers = $deadline['expired'] ? ($attempt->answers ?? []) : $answers;

        // SPEC §21's "Is required", enforced for the first time. It was stored,
        // snapshotted, and read by nothing — so a student could submit with
        // every required question blank, be scored zero on them, and never be
        // told they had skipped anything.
        //
        // Deliberately **not** applied to an expired attempt. §31's rule above
        // is that time running out scores what was in hand rather than throwing
        // it away; refusing a late submission for a blank question would strand
        // the student on a page they can no longer act on.
        if (! $deadline['expired']) {
            $missing = app(ListUnansweredRequiredQuestionsAction::class)
                ->execute($attempt->snapshots ?? [], $scoredAnswers);

            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'answers' => ['Answer the required questions first: '.implode(', ', $missing).'.'],
                ]);
            }
        }

        $result = app(ScoreAssessmentSnapshotsAction::class)->execute(
            $attempt->snapshots ?? [],
            $scoredAnswers,
            $settings['passing_score'] ?? null,
            // SPEC §19: an assessment the teacher marked as needing a human
            // waits for one, whatever its questions happen to be.
            (bool) ($settings['requires_teacher_marking'] ?? false),
        );

        $attempt->update([
            'answers' => $scoredAnswers,
            'status' => AssessmentAttemptStatus::from($result['status']),
            'score' => $result['score'],
            'max_score' => $result['max_score'],
            'submitted_at' => now(),
            'last_saved_at' => now(),
        ]);

        $showKeys = (bool) $settings['show_correct_answers'] && $result['status'] === 'scored';

        return [
            'attempt' => app(StartAssessmentAttemptAction::class)->serialize($attempt->fresh(), includeKeys: $showKeys, asStudent: true),
            'result' => $result,
            // Reported rather than silent: a student whose late answers were
            // dropped is owed an explanation, and a teacher looking at the
            // score needs to know why it stops where it does.
            'expired' => $deadline['expired'],
            'seconds_over' => $deadline['seconds_over'],
        ];
    }
}
