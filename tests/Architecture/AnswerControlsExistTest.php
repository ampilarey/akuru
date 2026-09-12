<?php

use App\Domains\Courses\Enums\ActivityPattern;

/**
 * Every answer pattern has a control the student can actually use.
 *
 * `arrange` questions were scorable (`scoreArrange`), authorable (the question
 * bank ships sample options for them) and mapped from `QuestionType::Arrange` —
 * and the **assessment** player had no branch for them. Worse than nothing:
 * `blankAnswers()` seeded `{order: …}` from the presented option order, so the
 * player submitted an answer on the student's behalf for a control it never
 * rendered. If the correct order happened to match the order shown, the student
 * scored full marks without acting; otherwise zero, with no way to change it.
 *
 * This is the fourth member of a family this codebase keeps meeting, and every
 * one looks identical from outside: **HTTP 200, and a page the user cannot
 * use.** Routes pointing at missing controller methods, `form.transform().post()`
 * silently not posting, `Inertia::render` naming a component that does not
 * exist — and now a question type with no input.
 *
 * Filesystem only: no database, no HTTP, no fixture.
 */
it('renders a control for every answer pattern in both players', function () {
    $players = [
        'resources/js/Pages/Courses/Learn/Assessment.jsx' => 'snapshot.pattern',
        'resources/js/Pages/Courses/Learn/Activity.jsx' => 'activity.pattern',
    ];

    $missing = [];

    foreach ($players as $path => $accessor) {
        $source = (string) file_get_contents(base_path($path));

        foreach (ActivityPattern::cases() as $pattern) {
            // The rendering branch, not merely a mention: `blankAnswers()`
            // referenced 'arrange' while rendering nothing for it, which is
            // exactly the bug this test exists to catch.
            $branch = "{$accessor} === '{$pattern->value}' &&";

            if (! str_contains($source, $branch)) {
                $missing[] = basename($path).' has no control for '.$pattern->value;
            }
        }
    }

    expect($missing)->toBeEmpty(
        "A student is shown these questions and cannot answer them:\n  "
        .implode("\n  ", $missing)
        ."\nAdd a rendering branch, or remove the pattern from ActivityPattern."
    );
});
