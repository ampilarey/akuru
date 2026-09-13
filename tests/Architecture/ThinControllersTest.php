<?php

/**
 * PHASE_0_CHECKLIST §0.5 rule 4, the half that never shipped:
 *
 *   > Controllers don't use `DB::` facade **and have no `private function`
 *   > business logic (heuristic: max method length)** — enforce on NEW
 *   > controllers only at first; legacy controllers get a baseline ignore-list
 *   > that may only shrink.
 *
 * The `DB::` half has been enforced since Phase 0 (`BaselineArchitectureTest`
 * rule 4). The length half was never written, in a rule listed as CI-blocking
 * "from day one" — and named twice more: ROADMAP §6's gate table says "thin
 * controllers", and SPEC §41 lists "controllers contain business logic that
 * should be in actions/services" among the four things the architecture suite
 * must fail CI on.
 *
 * **CLAUDE.md rule 5 is what it is protecting**: *authorize → validate into
 * DTO → call Action → return response*. A long controller method is the
 * measurable shadow of that rule being broken, and the cost is not tidiness —
 * it is that the rule ends up somewhere no test can reach it. This session has
 * twice moved logic out of a controller for exactly that reason: the
 * free-enrollment notices (64 lines, which had put mail composition in a
 * controller) and the "which enrollments are free" filter, which was an inline
 * `array_filter` deciding a rule 12 question.
 *
 * **Threshold: 36 lines of code.** That is the 95th percentile of the 1,116
 * controller methods here (median 11, p90 26), so it flags the top 5% rather
 * than legislating a style. It is a heuristic for "this method is doing work an
 * Action should own".
 *
 * **Lines of code, not lines of file.** The first version of this gate counted
 * the method's raw span, and within the hour it failed a change whose only
 * addition was a **two-line explanatory comment** —
 * `TeacherRegisterController::show — was 55, now 57`. That is the wrong
 * incentive in the most literal way: a gate that charges for documentation gets
 * less documentation. `tests/Support/SourceReadingHelpers.php` already carries
 * this lesson, learned three times before ("a check that cannot tell
 * documentation from instruction punishes writing the explanation down"), so
 * this one now strips comments with the same helper and counts only lines that
 * still have something on them. Blank lines are free too — a method spaced out
 * for readability is not a method doing too much.
 *
 * **The baseline records each method's current length, and it may only go
 * down.** A listed method that grows fails too — otherwise a 45-line method
 * could quietly become 300 and still count as known. Shrinking one below the
 * threshold means deleting its line, which is how the list empties.
 *
 * Filesystem only: no database, no HTTP, no fixture.
 */
it('keeps controllers thin, and lets the known long ones only get shorter', function () {
    $baseline = require __DIR__.'/Baselines/long_controller_methods.php';
    $threshold = 36;

    $current = controllerMethodLengths();

    $tooLong = array_filter($current, fn (int $lines) => $lines > $threshold);

    // 1. Nothing new may cross the line.
    $new = array_values(array_diff(array_keys($tooLong), array_keys($baseline)));
    sort($new);

    expect($new)->toBeEmpty(
        "These controller methods are over {$threshold} lines of code and are not in the baseline:\n  "
        .implode("\n  ", array_map(fn ($k) => $k.' ('.$tooLong[$k].' lines of code)', $new))
        ."\n\nCLAUDE.md rule 5: a controller authorizes, validates into a DTO, calls an "
        .'Action and returns a response. A method this long is doing work an Action should '
        ."own — and a rule that lives in a controller is a rule no test can reach.\n\n"
        .'Move the body into an Action. If it genuinely belongs here, add it to '
        .'tests/Architecture/Baselines/long_controller_methods.php with its code length. '
        .'Comments and blank lines are not counted, so explaining the method costs nothing.'
    );

    // 2. A baselined method may not grow.
    $grown = [];
    foreach ($baseline as $method => $was) {
        $now = $current[$method] ?? null;
        if ($now !== null && $now > $was) {
            $grown[] = "{$method} — was {$was}, now {$now}";
        }
    }
    sort($grown);

    expect($grown)->toBeEmpty(
        "These baselined controller methods got longer:\n  "
        .implode("\n  ", $grown)
        ."\n\nThe baseline records a length so it can only shrink. Growing one means the "
        .'method is accumulating exactly the logic this gate exists to move out.'
    );

    // 3. A method that has been fixed leaves the list, so the count stays honest.
    $fixed = [];
    foreach ($baseline as $method => $was) {
        $now = $current[$method] ?? null;
        if ($now === null || $now <= $threshold) {
            $fixed[] = $method.($now === null ? ' — gone' : " — now {$now} lines");
        }
    }
    sort($fixed);

    expect($fixed)->toBeEmpty(
        "These are no longer over the threshold — delete them from the baseline and correct its count:\n  "
        .implode("\n  ", $fixed)
        ."\n\nThat is the direction the list is meant to move."
    );
});

/**
 * Every controller method and how many **lines of code** it contains, keyed
 * `path/to/Controller.php::method`.
 *
 * Comments are stripped first (`stripPhpComments()` keeps the newlines, so the
 * line numbering survives) and blank lines are not counted. What is left is the
 * work the method actually does — which is what the threshold is about.
 *
 * Stripping first also makes the brace counting sounder: a `{` inside a comment
 * used to shift the depth. A brace inside a string literal still can, since the
 * helper deliberately keeps strings — this is a heuristic gate whose threshold
 * is a percentile, so exactness is not what it trades on.
 *
 * Brace-counted rather than parsed, for the same reason.
 *
 * @return array<string, int>
 */
function controllerMethodLengths(): array
{
    $lengths = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = str_replace(base_path().'/', '', $file->getPathname());
        if (! str_contains($path, '/Http/Controllers/')) {
            continue;
        }

        $lines = explode("\n", stripPhpComments(file_get_contents($file->getPathname())));
        $start = null;
        $name = null;
        $depth = 0;
        $opened = false;
        $code = 0;

        foreach ($lines as $line) {
            if ($start === null && preg_match('/(public|protected|private)\s+function\s+(\w+)\s*\(/', $line, $m)) {
                $start = true;
                $name = $m[2];
                $depth = 0;
                $opened = false;
                $code = 0;
            }

            if ($start === null) {
                continue;
            }

            if (trim($line) !== '') {
                $code++;
            }

            $depth += substr_count($line, '{') - substr_count($line, '}');
            if (str_contains($line, '{')) {
                $opened = true;
            }

            if ($opened && $depth <= 0) {
                $lengths[$path.'::'.$name] = $code;
                $start = null;
            }
        }
    }

    return $lengths;
}
