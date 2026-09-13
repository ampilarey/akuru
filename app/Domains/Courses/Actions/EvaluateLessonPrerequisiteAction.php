<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\UnlockMode;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Progress\Actions\ListAssessmentScoresAction;

/**
 * SPEC §26's "Pass quiz first", for one lesson and one student.
 *
 * §26 says "Create an `UnlockRuleEvaluator` service. Do not scatter unlock
 * logic across controllers or React components." `LessonUnlockEvaluator` is
 * that service and stays the only place that decides — but it is deliberately
 * handed **plain facts** rather than domain objects, because it lives in
 * Progress and must not know how Courses spells an assessment (rule 3). That
 * is why `$allOpen` is a bool there and not the `UnlockMode` enum.
 *
 * This Action answers the one question the evaluator cannot: has this student
 * passed the assessment the lesson names? It reads attempts through Progress's
 * own Action, so no Progress model is imported here either.
 *
 * §19's `settings.lock_next_lesson` was this idea written as a bare boolean —
 * written by two Actions, read by nothing, and unable to say *which* quiz.
 * Naming the assessment is what makes the rule enforceable, and it is why no
 * control was ever added for that boolean.
 */
class EvaluateLessonPrerequisiteAction
{
    /**
     * @return array{met: bool, mode: UnlockMode, assessment: ?string}
     */
    public function execute(Lesson $lesson, ?int $studentId): array
    {
        $rule = is_array($lesson->unlock_rule) ? $lesson->unlock_rule : [];
        $mode = UnlockMode::tryFrom((string) ($rule['mode'] ?? ''));

        // A lesson with no rule of its own inherits the course's, which the
        // sequence check already applies. Nothing to add here.
        if ($mode !== UnlockMode::PassAssessment) {
            return ['met' => true, 'mode' => $mode ?? UnlockMode::Sequential, 'assessment' => null];
        }

        $assessmentId = (int) ($rule['assessment_id'] ?? 0);
        $assessment = $assessmentId > 0
            ? Assessment::query()->find($assessmentId)
            : null;

        if ($assessment === null) {
            // The named assessment is gone. Locking every student out of a
            // lesson because its prerequisite was deleted punishes them for an
            // author's edit, so the rule lapses rather than bites.
            return ['met' => true, 'mode' => UnlockMode::PassAssessment, 'assessment' => null];
        }

        if ($studentId === null) {
            return ['met' => false, 'mode' => UnlockMode::PassAssessment, 'assessment' => (string) $assessment->title];
        }

        $met = $this->hasPassed($assessment, $studentId);

        return [
            'met' => $met,
            'mode' => UnlockMode::PassAssessment,
            'assessment' => (string) $assessment->title,
        ];
    }

    /**
     * A *final* mark that reaches the passing bar.
     *
     * `is_final` matters for the same reason it did in §27's certificate
     * eligibility: a submitted attempt carries a provisional auto-score
     * waiting for a teacher, and unlocking on it would open a lesson that a
     * later marking could close again.
     *
     * The passing bar is read the way the rest of the codebase reads it — as a
     * percentage when it exceeds `max_score`, which `TeacherReviewReportTest`
     * pins as deliberate for legacy rows expressing a percent on a small-max
     * quiz. Sharing that reading is the point: a lesson must not unlock on a
     * different definition of "passed" than the report shows.
     */
    private function hasPassed(Assessment $assessment, int $studentId): bool
    {
        $scores = app(ListAssessmentScoresAction::class)->execute([(int) $assessment->id], [$studentId]);

        foreach ($scores as $byStudent) {
            $row = $byStudent[$studentId] ?? null;
            if ($row === null || ! ($row['is_final'] ?? false)) {
                continue;
            }

            $score = $row['score'] ?? null;
            $max = $row['max_score'] ?? null;
            if ($score === null) {
                continue;
            }

            $passing = $assessment->passing_score;
            if ($passing === null) {
                // No bar to clear: a final mark of any size counts.
                return true;
            }

            $percent = ($max !== null && (float) $max > 0.0)
                ? ((float) $score / (float) $max) * 100.0
                : null;

            $bar = ($max !== null && (int) $passing > (int) $max)
                ? ['percent', (float) $passing]
                : ['points', (float) $passing];

            if ($bar[0] === 'percent') {
                if ($percent !== null && $percent >= $bar[1]) {
                    return true;
                }

                continue;
            }

            if ((float) $score >= $bar[1]) {
                return true;
            }
        }

        return false;
    }
}
