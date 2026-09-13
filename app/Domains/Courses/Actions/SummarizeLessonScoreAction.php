<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Progress\Actions\ListActivityAttemptsByIdsAction;

/**
 * SPEC §25 lists `score_summary` among the fields of `student_lesson_progress`.
 *
 * The column shipped. The model casts it. `RecordLessonProgressAction` even
 * accepts it — `'score_summary' => $data['score_summary'] ?? $row->score_summary`
 * — and **no caller has ever passed one**, so every progress row in the system
 * records that a lesson was finished and nothing whatsoever about how.
 *
 * That matters more than a missing convenience. §25 puts the field on the
 * progress row rather than leaving it derivable, and the reason is the same one
 * §21 gives for snapshotting a question: the row is a record of what happened,
 * and an activity re-attempted, re-marked or deleted afterwards must not
 * silently rewrite a lesson a student completed in March.
 *
 * The summary is built from the attempts `EvaluateLessonCompletionAction`
 * already reads to decide whether completion is allowed, so the figure stored
 * is the same evidence the decision was made on.
 */
class SummarizeLessonScoreAction
{
    /**
     * Null rather than an empty shell when there is nothing to summarise: a
     * lesson of pure reading has no score, and writing `{score: null}` on every
     * such row would make the column look populated while saying nothing.
     *
     * @return array<string, mixed>|null
     */
    public function execute(Lesson $lesson, ?int $enrollmentId): ?array
    {
        if ($enrollmentId === null) {
            return null;
        }

        $activities = Activity::query()
            ->where('lesson_id', $lesson->id)
            // `activities` has no position column — the lesson's own ordering
            // is the outline's, not the activity table's — so id order is the
            // stable one available.
            ->orderBy('id')
            ->get(['id', 'title', 'is_required']);

        if ($activities->isEmpty()) {
            return null;
        }

        // Rule 3: attempts belong to Progress, and Courses reads them through
        // that domain's Action rather than its models.
        $attempts = app(ListActivityAttemptsByIdsAction::class)->execute(
            $activities->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            $enrollmentId,
        );

        // `ListActivityAttemptsByIdsAction` orders by `submitted_at` descending,
        // so the first row per activity is the latest attempt — the one a
        // student would recognise as their result.
        $latest = [];
        foreach ($attempts as $attempt) {
            $activityId = (int) $attempt['activity_id'];
            if (! array_key_exists($activityId, $latest)) {
                $latest[$activityId] = $attempt;
            }
        }

        if ($latest === []) {
            return null;
        }

        $rows = [];
        $score = 0.0;
        $max = 0.0;
        $scored = false;

        foreach ($activities as $activity) {
            $attempt = $latest[(int) $activity->id] ?? null;
            if ($attempt === null) {
                continue;
            }

            $rows[] = [
                'activity_id' => (int) $activity->id,
                'title' => (string) $activity->title,
                'is_required' => (bool) $activity->is_required,
                'status' => $attempt['status'] ?? null,
                'score' => $attempt['score'],
                'max_score' => $attempt['max_score'],
                'attempt_number' => $attempt['attempt_number'] ?? null,
            ];

            if ($attempt['score'] !== null && $attempt['max_score'] !== null) {
                $score += (float) $attempt['score'];
                $max += (float) $attempt['max_score'];
                $scored = true;
            }
        }

        if ($rows === []) {
            return null;
        }

        return [
            'activities' => $rows,
            'score' => $scored ? $score : null,
            'max_score' => $scored ? $max : null,
            // Stored rather than left to the reader, because the parts it is
            // derived from are exactly what may change afterwards.
            'percent' => $scored && $max > 0.0 ? (int) floor(($score / $max) * 100) : null,
            'recorded_at' => now()->toIso8601String(),
        ];
    }
}
