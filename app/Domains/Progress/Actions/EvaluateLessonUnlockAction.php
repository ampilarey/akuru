<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Contracts\LessonUnlockEvaluator;

class EvaluateLessonUnlockAction implements LessonUnlockEvaluator
{
    /**
     * SPEC §26's two data-free rules: "All lessons open" and "Complete
     * previous lesson first". Preview lessons are always open.
     *
     * The remaining nine rules §26 lists each need a source of truth this
     * evaluator is not handed — a quiz result, a teacher's approval, a
     * payment, a date. They are deliberately absent rather than stubbed.
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
    ): bool {
        if ($isPreview) {
            return true;
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
