<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\UnlockMode;
use App\Domains\Courses\Models\Course;

class ResolveCourseUnlockModeAction
{
    /**
     * SPEC §26: "Unlock rules should be stored in JSON settings at course,
     * module, lesson, or offering level."
     *
     * Course level only, for now — that is the level which answers the
     * question that was impossible to answer at all ("is this course a
     * sequence, or a set of independent pages"). Module, lesson and offering
     * overrides read through this same action when they arrive, so callers do
     * not learn a second way to ask.
     *
     * An unreadable or unknown value resolves to the default rather than
     * throwing: a malformed setting must not lock every student out of a
     * course, and the default is the stricter of the two modes.
     */
    public function execute(?Course $course): UnlockMode
    {
        $rules = $course?->unlock_rules;
        if (! is_array($rules)) {
            return UnlockMode::default();
        }

        $mode = $rules['mode'] ?? null;

        return is_string($mode)
            ? (UnlockMode::tryFrom($mode) ?? UnlockMode::default())
            : UnlockMode::default();
    }
}
