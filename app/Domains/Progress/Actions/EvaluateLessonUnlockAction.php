<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Contracts\LessonUnlockEvaluator;

class EvaluateLessonUnlockAction implements LessonUnlockEvaluator
{
    /**
     * Sequential required-lesson unlock. Preview lessons are always open.
     *
     * @param  list<int>  $requiredLessonIdsInOrder
     * @param  list<int>  $completedLessonIds
     */
    public function execute(int $lessonId, array $requiredLessonIdsInOrder, array $completedLessonIds, bool $isPreview = false): bool
    {
        if ($isPreview) {
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
