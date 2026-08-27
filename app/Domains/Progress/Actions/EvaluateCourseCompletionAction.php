<?php

namespace App\Domains\Progress\Actions;

use App\Domains\Progress\Contracts\CourseCompletionEvaluator;

class EvaluateCourseCompletionAction implements CourseCompletionEvaluator
{
    /**
     * Required lessons plus optional required sessions.
     *
     * @param  list<int>  $requiredLessonIds
     * @param  list<int>  $completedLessonIds
     * @param  list<int>  $requiredSessionIds
     * @param  list<int>  $attendedSessionIds
     * @return array{completed_required: int, total_required: int, percentage: int, is_complete: bool}
     */
    public function execute(
        array $requiredLessonIds,
        array $completedLessonIds,
        array $requiredSessionIds = [],
        array $attendedSessionIds = [],
    ): array {
        $lessons = array_values(array_unique(array_map('intval', $requiredLessonIds)));
        $sessions = array_values(array_unique(array_map('intval', $requiredSessionIds)));
        $completedLessons = array_map('intval', $completedLessonIds);
        $attended = array_map('intval', $attendedSessionIds);

        $lessonDone = count(array_intersect($lessons, $completedLessons));
        $sessionDone = count(array_intersect($sessions, $attended));
        $completed = $lessonDone + $sessionDone;
        $total = count($lessons) + count($sessions);
        $percentage = app(CalculateCourseProgressAction::class)->execute($completed, $total);

        return [
            'completed_required' => $completed,
            'total_required' => $total,
            'percentage' => $percentage,
            'is_complete' => $total > 0 && $completed === $total,
        ];
    }
}
