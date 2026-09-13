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
 * **Threshold: 40 lines.** That is the 95th percentile of the 1,116 controller
 * methods here (median 13, p90 31), so it flags the top 5% rather than
 * legislating a style. It is a heuristic for "this method is doing work an
 * Action should own".
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
    $threshold = 40;

    $current = controllerMethodLengths();

    $tooLong = array_filter($current, fn (int $lines) => $lines > $threshold);

    // 1. Nothing new may cross the line.
    $new = array_values(array_diff(array_keys($tooLong), array_keys($baseline)));
    sort($new);

    expect($new)->toBeEmpty(
        "These controller methods are over {$threshold} lines and are not in the baseline:\n  "
        .implode("\n  ", array_map(fn ($k) => $k.' ('.$tooLong[$k].' lines)', $new))
        ."\n\nCLAUDE.md rule 5: a controller authorizes, validates into a DTO, calls an "
        .'Action and returns a response. A method this long is doing work an Action should '
        ."own — and a rule that lives in a controller is a rule no test can reach.\n\n"
        .'Move the body into an Action. If it genuinely belongs here, add it to '
        .'tests/Architecture/Baselines/long_controller_methods.php with its length.'
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
 * Every controller method and how many lines it spans, keyed
 * `path/to/Controller.php::method`.
 *
 * Brace-counted rather than parsed: a real parser would be better, and this is
 * a heuristic gate whose threshold is a percentile, so exactness is not what it
 * trades on.
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

        $lines = file($file->getPathname());
        $start = null;
        $name = null;
        $depth = 0;
        $opened = false;

        foreach ($lines as $i => $line) {
            if ($start === null && preg_match('/(public|protected|private)\s+function\s+(\w+)\s*\(/', $line, $m)) {
                $start = $i;
                $name = $m[2];
                $depth = 0;
                $opened = false;
            }

            if ($start === null) {
                continue;
            }

            $depth += substr_count($line, '{') - substr_count($line, '}');
            if (str_contains($line, '{')) {
                $opened = true;
            }

            if ($opened && $depth <= 0) {
                $lengths[$path.'::'.$name] = $i - $start + 1;
                $start = null;
            }
        }
    }

    return $lengths;
}
