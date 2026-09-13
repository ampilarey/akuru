<?php

/**
 * CLAUDE.md rule 10 — the backbone rule — which until now nothing enforced:
 *
 *   > Any new table recording something that happens in time (attendance,
 *   > marks, invoices, sessions) carries `academic_year_id` (and `term_id`
 *   > where relevant).
 *
 * S1_SPEC §S1.5 rule 1 states it as a *"standing rule for S2+"*, and S1's
 * Definition of Done says it is to be *"documented in ROADMAP risk notes and
 * enforced in **code review checklist**"* — that is, by a human remembering.
 * Nothing failed CI when one was forgotten.
 *
 * And one was forgotten, repeatedly and inside slices that got it right two
 * tables away:
 *
 *  - `pickup_notices` and `pickup_windows` are created **forty lines apart in
 *    one migration** (E8, 2026-09-11). The notice carries `academic_year_id`.
 *    The window, which is literally a row per date, does not.
 *  - `term_grades` and `competency_assessments` are created in one migration
 *    (S3.4). The first carries both `term_id` and `academic_year_id`. The
 *    second carries `term_id` alone — the exact shape rule 10's parenthetical
 *    rules out, since a term already belongs to a year.
 *  - `report_cards` (S3.6) carries `term_id` and no year either.
 *
 * That is the signature of a rule with nothing to enforce it: not systematic
 * disagreement, just drift within a single sitting.
 *
 * ## What counts as "happens in time"
 *
 * Two signals, both read off the create-migration, both deliberately narrow:
 *
 *  1. **A business date column** — a `date`/`dateTime` column named `date`,
 *     `day`, or `*_date`. Lifecycle timestamps (`created_at`, `sent_at`,
 *     `published_at`, `verified_at`) are *not* counted: almost every table has
 *     one, and "when the row was touched" is not the same as "the day the
 *     thing happened". A column the schema calls a date is the author saying
 *     the row is about a day.
 *  2. **A `term_id`** — rule 10's own parenthetical. A row scoped to a term is
 *     scoped to that term's year, and storing only the term means every
 *     year-level query has to join to find out.
 *
 * 23 tables already carry the backbone. Of the 29 that do not, 13 fall outside
 * the rule (a profile, a catalogue entry, website content, telemetry) and 9 are
 * superseded legacy tables — leaving 7 real misses against 23 compliant. This
 * is not an aspiration being retrofitted: it is the convention, written down.
 *
 * ## The baseline
 *
 * `tables_without_academic_backbone.php` lists the tables that predate the
 * rule or genuinely fall outside it, each with **why** — a profile record, a
 * catalogue attribute, website content, telemetry, or an honest "rule 10 miss,
 * needs an additive migration". It may only shrink: a table that gains the
 * column must leave the list, and a table that no longer exists must too.
 *
 * The entries marked as misses are the ones that should go first. They are
 * recorded rather than fixed here because adding the column to a populated
 * table is rule 9's three-deploy dance — additive migration, backfill from
 * `terms.academic_year_id`, then switch the writers — which is its own slice,
 * not a line in a test's changelog (rule 1).
 *
 * Filesystem only: no database, no HTTP, no fixture.
 */
it('requires the academic backbone on tables that record something happening in time', function () {
    $baseline = require __DIR__.'/Baselines/tables_without_academic_backbone.php';

    $tables = timeScopedTableBackbone();

    $missing = [];
    foreach ($tables as $table => $facts) {
        // The table that defines the years cannot carry a foreign key to
        // itself, and `terms` already does.
        if ($table === 'academic_years') {
            continue;
        }

        if ($facts['has_year'] || $facts['signals'] === []) {
            continue;
        }

        $missing[$table] = $facts;
    }

    // 1. A new time-scoped table must carry the backbone.
    $new = array_values(array_diff(array_keys($missing), array_keys($baseline)));
    sort($new);

    expect($new)->toBeEmpty(
        "These tables record something that happens in time and have no academic_year_id:\n  "
        .implode("\n  ", array_map(
            fn ($t) => $t.' — '.implode('; ', $missing[$t]['signals']).' ('.$missing[$t]['migration'].')',
            $new
        ))
        ."\n\nCLAUDE.md rule 10: any new table recording something that happens in time carries "
        .'academic_year_id (and term_id where relevant). Without it, every year-level report has '
        .'to infer the year from a date range or a join, and rolling over to the next academic '
        ."year silently mixes the two.\n\n"
        .'Add the column, or — if the row genuinely is not academic-year scoped — add the table to '
        .'tests/Architecture/Baselines/tables_without_academic_backbone.php with the reason.'
    );

    // 2. A table that has since gained the column leaves the list, so the
    //    count is always the real count.
    $fixed = [];
    foreach ($baseline as $table => $reason) {
        if (! isset($tables[$table])) {
            $fixed[] = $table.' — no such table is created any more';

            continue;
        }

        if ($tables[$table]['has_year']) {
            $fixed[] = $table.' — now carries academic_year_id';

            continue;
        }

        if ($tables[$table]['signals'] === []) {
            $fixed[] = $table.' — no longer has a business date column or a term_id';
        }
    }
    sort($fixed);

    expect($fixed)->toBeEmpty(
        "These baseline entries are stale — delete them:\n  "
        .implode("\n  ", $fixed)
        ."\n\nThe list may only shrink. That is the whole mechanism."
    );
});

/**
 * Every table a migration creates, with the two rule-10 signals and whether it
 * carries `academic_year_id`.
 *
 * Read off the create-migrations rather than the live schema so the gate needs
 * no database and fails in the pull request that introduces the table, not on
 * the deploy after. A later `Schema::table()` that adds the column counts —
 * that is how a table leaves the baseline.
 *
 * @return array<string, array{has_year: bool, signals: list<string>, migration: string}>
 */
function timeScopedTableBackbone(): array
{
    $files = [];

    $app = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($app as $file) {
        if ($file->isFile() && $file->getExtension() === 'php' && str_contains($file->getPathname(), '/migrations/')) {
            $files[] = $file->getPathname();
        }
    }

    foreach (glob(base_path('database/migrations/*.php')) as $file) {
        $files[] = $file;
    }

    // Migrations apply in filename order, so a column added by a later
    // `Schema::table()` must be seen after the create that lacks it.
    usort($files, fn ($a, $b) => strcmp(basename($a), basename($b)));

    $created = [];
    $hasYear = [];
    $signals = [];

    foreach ($files as $file) {
        $source = file_get_contents($file);
        $relative = str_replace(base_path().'/', '', $file);

        // Only `up()` describes the schema. `down()` is full of the mirror
        // image — `invoices` gained `academic_year_id` in S4.1 and its
        // rollback drops it, which read naively says the column is not there.
        if (($rollback = strpos($source, 'function down(')) !== false) {
            $source = substr($source, 0, $rollback);
        }

        if (! preg_match_all("/Schema::(create|table)\(\s*'([a-z0-9_]+)'/", $source, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        for ($i = 0; $i < count($matches[0]); $i++) {
            $kind = $matches[1][$i][0];
            $table = $matches[2][$i][0];
            $start = $matches[0][$i][1];
            $end = ($i + 1 < count($matches[0])) ? $matches[0][$i + 1][1] : strlen($source);
            $block = substr($source, $start, $end - $start);

            if ($kind === 'create') {
                $created[$table] = $relative;
                $hasYear[$table] = false;
                $signals[$table] = [];
            }

            if (! isset($created[$table])) {
                continue;
            }

            if (str_contains($block, "'academic_year_id'")) {
                $hasYear[$table] = ! preg_match("/dropColumn\(\s*\[?\s*'academic_year_id'/", $block);
            }

            if (str_contains($block, "'term_id'")) {
                $signals[$table]['term'] = 'has term_id';
            }

            if (preg_match_all("/->(?:date|dateTime|dateTimeTz)\(\s*'([a-z0-9_]+)'/", $block, $columns)) {
                foreach ($columns[1] as $column) {
                    if (preg_match('/^(date|day)$|_date$/', $column)) {
                        $signals[$table]['date'] = 'business date column';
                    }
                }
            }
        }
    }

    $backbone = [];
    foreach ($created as $table => $migration) {
        $backbone[$table] = [
            'has_year' => $hasYear[$table],
            'signals' => array_values($signals[$table]),
            'migration' => $migration,
        ];
    }

    ksort($backbone);

    return $backbone;
}
