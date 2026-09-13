<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Contracts\LessonUnlockEvaluator;

class EvaluateLessonUnlockAction implements LessonUnlockEvaluator
{
    /**
     * SPEC §26's two data-free rules: "All lessons open" and "Complete
     * previous lesson first". Preview lessons are always open.
     *
     * Plus §26's "Pass quiz first", which needs a source of truth this
     * evaluator is not handed — so it arrives as a plain `$prerequisiteMet`
     * fact, the same shape as `$allOpen`, worked out by Courses before the
     * call. Progress still has no idea what an assessment is.
     *
     * The remaining eight rules §26 lists each need their own source of truth
     * — a teacher's approval, a payment, a date, an attendance record. They
     * are deliberately absent rather than stubbed.
     *
     * @param  list<int>  $requiredLessonIdsInOrder
     * @param  list<int>  $completedLessonIds
     */
    public function execute(
        int $lessonId,
        array $requiredLessonIdsInOrder,
        array $completedLessonIds,
        bool $isPreview = false,
        bool $allOpen = false,
        bool $prerequisiteMet = true,
    ): bool {
        if ($isPreview) {
            return true;
        }

        // §26 "Pass quiz first". Checked before `$allOpen`: a lesson that
        // names a prerequisite means it in a course where everything else is
        // open, which is exactly the case an all-open reference course with
        // one gated chapter needs.
        if (! $prerequisiteMet) {
            return false;
        }

        // SPEC §26's first listed rule, and the one that was unreachable: a
        // reference course or a set of independent pages had no way to say
        // that a student may start anywhere.
        if ($allOpen) {
            return true;
        }

        foreach (array_values(array_map('intval', $requiredLessonIdsInOrder)) as $id) {
            if ($id === $lessonId) {
                return true;
            }
            if (! in_array($id, $completedLessonIds, true)) {
                return false;
            }
        }

        return false;
    }
}
