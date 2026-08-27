<?php

namespace App\Domains\Progress\Contracts;

/**
 * ROADMAP §2a / spec §42 extension point: how a course decides a student is done.
 *
 * The only implementation today counts required lessons plus required sessions
 * (see EvaluateCourseCompletionAction). The Hifz → engine migration (ROADMAP §2b,
 * Phase F) is the named second consumer: it maps memorisation milestones onto
 * completion rules, and it should bind its own implementation here rather than
 * teach the engine what a milestone is.
 *
 * ADR-022 records why the interface exists before its second implementation.
 */
interface CourseCompletionEvaluator
{
    /**
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
    ): array;
}
