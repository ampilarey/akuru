<?php

namespace App\Domains\Progress\Contracts;

/**
 * ROADMAP §2a / spec §42 extension point: how a course decides whether a lesson
 * is open to a student.
 *
 * The engine must never branch on course type to answer this (rule 6). A new
 * pedagogy — date-drip, prerequisite graph, teacher-released — is a new
 * implementation bound in place of the default, not an `if` inside Courses.
 *
 * The only implementation today is sequential (see EvaluateLessonUnlockAction).
 * ADR-022 records why the interface exists before its second implementation.
 */
interface LessonUnlockEvaluator
{
    /**
     * @param  list<int>  $requiredLessonIdsInOrder
     * @param  list<int>  $completedLessonIds
     * @param  bool  $allOpen  SPEC §26's first listed rule, "All lessons open".
     *                         Passed as a plain fact rather than the Courses
     *                         `UnlockMode` enum: Progress does not need to know
     *                         how a course spells its unlock settings, only
     *                         whether the sequence applies (rule 3).
     */
    public function execute(
        int $lessonId,
        array $requiredLessonIdsInOrder,
        array $completedLessonIds,
        bool $isPreview = false,
        bool $allOpen = false,
    ): bool;
}
