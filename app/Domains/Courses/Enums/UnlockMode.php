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
        };
    }
}
