<?php

/**
 * A soft-deleted row is invisible to Eloquent and perfectly visible to the
 * query builder.
 *
 * `ReserveOfferingSeatAction` carries this lesson already, in a comment written
 * after it bit somebody:
 *
 *   > *`course_enrollments` soft-deletes (§29), and this counts through the
 *   > query builder for the row locks — which knows nothing about the trait. A
 *   > soft-deleted enrolment was holding its seat forever.*
 *
 * The lesson stayed in that one file. A sweep of every `DB::table()` read
 * against a soft-deleting table found three more places it had not reached:
 *
 *  - `ListPublishedAssessmentsAction` — a deleted assessment stayed on offer in
 *    all three pickers it feeds (course outline, offering list,
 *    certificate-issue options).
 *  - `UnifyStudentsAction::createFromRegistration` and
 *    `DualWriteCourseStudentAction` — both decide a unified student's status
 *    from `course_enrollments.status = 'active'`, so a student whose only
 *    enrolment had been **withdrawn** was unified as `Active` rather than
 *    `Prospective`. That is the one backfill that runs against real data, gated
 *    by a verification script.
 *
 * ## What this checks
 *
 * Every `DB::table('<a soft-deleting table>')` statement that **reads** must
 * either mention `deleted_at` or be in the baseline with a reason. Writes are
 * not checked: an `update()` or `insert()` is not a visibility question.
 *
 * The tables are read from the migrations rather than listed here, so a table
 * that gains `deleted_at` tomorrow is covered tomorrow.
 *
 * ## The baseline is mostly legitimate
 *
 * Several of these reads **should** ignore soft deletes — a certificate issued
 * for a course that was later deleted still has to print the course's name.
 * Each entry says which it is, and the list may only shrink.
 */
it('does not let a raw read forget that a table soft-deletes', function () {
    $baseline = require __DIR__.'/Baselines/raw_reads_ignoring_soft_deletes.php';

    $offenders = rawReadsIgnoringSoftDeletes();

    $new = array_values(array_diff(array_keys($offenders), array_keys($baseline)));
    sort($new);

    expect($new)->toBeEmpty(
        "These read a soft-deleting table through the query builder without mentioning deleted_at:\n  "
        .implode("\n  ", array_map(fn ($k) => $k.'  —  '.$offenders[$k], $new))
        ."\n\nEloquent hides soft-deleted rows; `DB::table()` does not. Add "
        ."`->whereNull('deleted_at')`, or add the site to "
        .'tests/Architecture/Baselines/raw_reads_ignoring_soft_deletes.php with the reason it '
        .'genuinely wants deleted rows — a certificate printing the name of a deleted course, say.'
    );

    $fixed = array_values(array_diff(array_keys($baseline), array_keys($offenders)));
    sort($fixed);

    expect($fixed)->toBeEmpty(
        "These baseline entries are stale — delete them:\n  "
        .implode("\n  ", $fixed)
        ."\n\nThe list may only shrink."
    );
});

/**
 * `path:line` => the statement, for every raw read of a soft-deleting table
 * that never mentions `deleted_at`.
 *
 * @return array<string, string>
 */
function rawReadsIgnoringSoftDeletes(): array
{
    $softDeleting = softDeletingTables();
    if ($softDeleting === []) {
        return [];
    }

    $pattern = "/DB::table\('(".implode('|', $softDeleting).")'\)/";
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = stripPhpComments(file_get_contents($file->getPathname()));
        $path = str_replace(base_path().'/', '', $file->getPathname());

        if (! preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($matches[0] as $index => $match) {
            $start = $match[1];
            $end = strpos($source, ';', $start);
            $statement = substr($source, $start, ($end !== false ? $end - $start : 400));

            if (str_contains($statement, 'deleted_at')) {
                continue;
            }

            // A write is not a visibility question.
            if (preg_match('/->(insert|insertGetId|update|updateOrInsert|upsert|delete|truncate)\(/', $statement)) {
                continue;
            }

            $line = substr_count(substr($source, 0, $start), "\n") + 1;
            $offenders[$path.':'.$line] = trim(preg_replace('/\s+/', ' ', $statement));
        }
    }

    ksort($offenders);

    return $offenders;
}

/**
 * Tables whose create-migration declares `softDeletes()`, read from the
 * migrations so a table that gains one tomorrow is covered tomorrow.
 *
 * @return list<string>
 */
function softDeletingTables(): array
{
    $tables = [];
    $files = glob(base_path('database/migrations/*.php'));

    $appMigrations = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
    );
    foreach ($appMigrations as $file) {
        if ($file->isFile() && $file->getExtension() === 'php' && str_contains($file->getPathname(), '/migrations/')) {
            $files[] = $file->getPathname();
        }
    }

    foreach ($files as $file) {
        $source = file_get_contents($file);

        if (($rollback = strpos($source, 'function down(')) !== false) {
            $source = substr($source, 0, $rollback);
        }

        if (! preg_match_all("/Schema::(?:create|table)\(\s*'([a-z0-9_]+)'/", $source, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        for ($i = 0; $i < count($matches[0]); $i++) {
            $start = $matches[0][$i][1];
            $end = ($i + 1 < count($matches[0])) ? $matches[0][$i + 1][1] : strlen($source);

            if (str_contains(substr($source, $start, $end - $start), 'softDeletes(')) {
                $tables[] = $matches[1][$i][0];
            }
        }
    }

    return array_values(array_unique($tables));
}
