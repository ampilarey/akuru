<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\LessonCompletionMode;
use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Progress\Actions\ListActivityAttemptsByIdsAction;

/**
 * SPEC §27: "Use a dedicated completion calculation service. Do not put
 * completion rules directly inside controllers."
 *
 * There was no such service for lessons, and no rule to calculate: the
 * controller posted `completed` and the progress writer stored it. This is
 * that service for the lesson level.
 *
 * The engine stays subject-ignorant (rule 6) — nothing here branches on
 * course type, and the rule is read from the lesson row rather than inferred
 * from what the lesson contains.
 */
class EvaluateLessonCompletionAction
{
    /**
     * @return array{allowed: bool, mode: LessonCompletionMode, outstanding: list<string>}
     */
    public function execute(Lesson $lesson, ?int $enrollmentId): array
    {
        $mode = $this->mode($lesson);

        if ($mode === LessonCompletionMode::Click || $enrollmentId === null) {
            return ['allowed' => true, 'mode' => $mode, 'outstanding' => []];
        }

        $required = Activity::query()
            ->where('lesson_id', $lesson->id)
            ->where('is_required', true)
            ->get(['id', 'title']);

        if ($required->isEmpty()) {
            // A rule demanding required activities on a lesson that has none
            // is satisfied, not impossible. Refusing here would strand every
            // lesson whose activities were later removed.
            return ['allowed' => true, 'mode' => $mode, 'outstanding' => []];
        }

        // Rule 3: attempts belong to Progress, and Courses reads them through
        // that domain's Action rather than its models.
        $attempted = app(ListActivityAttemptsByIdsAction::class)
            ->execute($required->pluck('id')->map(fn ($id): int => (int) $id)->all(), $enrollmentId)
            ->pluck('activity_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        $outstanding = $required
            ->reject(fn (Activity $activity): bool => in_array((int) $activity->id, $attempted, true))
            ->map(fn (Activity $activity): string => (string) $activity->title)
            ->values()
            ->all();

        return [
            'allowed' => $outstanding === [],
            'mode' => $mode,
            'outstanding' => $outstanding,
        ];
    }

    /**
     * Null, an unknown value, or a malformed rule all mean the default. A
     * lesson must never become uncompletable because its rule was written by
     * a newer version of the app than the one reading it.
     */
    public function mode(Lesson $lesson): LessonCompletionMode
    {
        $rule = $lesson->completion_rule;
        if (! is_array($rule)) {
            return LessonCompletionMode::Click;
        }

        return LessonCompletionMode::tryFrom((string) ($rule['mode'] ?? '')) ?? LessonCompletionMode::Click;
    }
}
