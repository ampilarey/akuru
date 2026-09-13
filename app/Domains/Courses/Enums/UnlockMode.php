<?php

namespace App\Domains\Courses\Enums;

/**
 * SPEC §26 lists eleven unlock rules. Two of them need no data beyond what the
 * engine already has, and those are the two this enum carries:
 *
 *   > All lessons open
 *   > Complete previous lesson first
 *
 * The other nine — pass quiz first, submit assignment first, teacher approval,
 * date-based, offering start date, session attendance, payment, manual unlock —
 * each need a source of truth the evaluator is not given today. They are not
 * stubbed here: an enum case that silently behaves like `sequential` would be
 * worse than its absence, because a course could be configured to require
 * teacher approval and quietly not.
 */
enum UnlockMode: string
{
    case AllOpen = 'all_open';
    case Sequential = 'sequential';

    /**
     * SPEC §26's "Pass quiz first", stored on the lesson as
     * `{"mode": "pass_assessment", "assessment_id": N}`.
     *
     * The third of §26's eleven rules to be built, and the first that needs a
     * source of truth beyond lesson completions. §19's
     * `settings.lock_next_lesson` was the same intent written as a bare
     * boolean — written by two Actions, read by nothing, and unable to say
     * *which* quiz. Naming the assessment is what makes it enforceable.
     */
    case PassAssessment = 'pass_assessment';

    /**
     * Sequential, because that is what every course did before §26 became
     * configurable. A course saved without an explicit mode must not change
     * behaviour on deploy (rule 9).
     */
    public static function default(): self
    {
        return self::Sequential;
    }

    public function label(): string
    {
        return match ($this) {
            self::AllOpen => 'All lessons open',
            self::Sequential => 'Complete previous lesson first',
            self::PassAssessment => 'Pass a quiz first',
        };
    }

    /**
     * The rules a **course** may set for all its lessons.
     *
     * `PassAssessment` is not among them, and keeping that straight matters:
     * §26 stores unlock rules "at course, module, lesson, or offering level",
     * and "pass quiz first" names a specific assessment, which is a statement
     * about one lesson rather than about a whole course. Adding the case
     * without this split leaked it into the course picker — caught by
     * `CourseUnlockModeTest`, which is what that test is for.
     *
     * @return list<self>
     */
    public static function courseLevelCases(): array
    {
        return [self::AllOpen, self::Sequential];
    }

    /**
     * The rules a single **lesson** may set, overriding its course.
     *
     * @return list<self>
     */
    public static function lessonLevelCases(): array
    {
        return [self::PassAssessment];
    }
}
