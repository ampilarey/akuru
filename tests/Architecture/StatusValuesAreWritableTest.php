<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Every value a status column allows can actually be written.
 *
 * ## Why this exists
 *
 * On 2026-09-14, three separate slices in one afternoon each turned out to be
 * the same defect wearing different clothes:
 *
 *  - `teachers.status` (`active`/`inactive`/`terminated`) was written **once**,
 *    at creation, always `active`. Nothing could ever change it.
 *  - `staff_profiles.status` had a route and validation that accepted `ended`
 *    and **no form anywhere that posted to it**.
 *  - `hifz_enrollments.status` had four values and a controller with `index`,
 *    `create` and `store` — no update path at all.
 *
 * Each of those columns was guarded by correct-looking filters —
 * `where('status', 'active')` in four places for the teachers one — that could
 * never exclude anybody, because the column could not move. Nothing failed.
 * No test noticed. They were found by trying to do the thing in a browser and
 * looking for the button.
 *
 * That is a defect class, not three accidents, and this is the check for it.
 *
 * ## What it claims, precisely
 *
 * For each `enum` status column, every value **other than the default** must
 * appear somewhere in the code that touches that table — as a literal, an enum
 * case, a member of an `in:a,b,c` validation list, or a form option.
 *
 * It is deliberately **permissive**: a mention is not a write, so a value can
 * pass here and still be unwritable in practice. The one thing it can say
 * soundly is the one thing that matters — *nothing anywhere so much as names
 * this value, so nothing can possibly write it.* All three of the defects
 * above are caught by that test.
 *
 * **Comments are stripped first.** Without that, a gate that looks for a word
 * is silenced by writing a sentence about the word — including the sentence
 * explaining why it is missing. Two of the columns below reappeared once
 * comment-stripping was added, having been hidden by prose written earlier the
 * same day.
 *
 * ## The baseline may only shrink
 *
 * A new enum column whose values nothing writes fails here. So does a
 * baselined value that has since become reachable — the entry must then be
 * removed, because a list that keeps stale entries stops being read.
 */
it('can write every value its status columns allow', function () {
    /**
     * Values no code path can reach today, with what is missing.
     *
     * Each was verified by this test's own evidence: the value does not appear
     * in **any** file that mentions its table or model, comments excluded.
     * What the reason does *not* claim is whether the omission is deliberate —
     * that is the owner's to say, and several of these read as capabilities
     * nobody has built rather than values nobody wants.
     */
    $baseline = [
        // Admissions: an application can be created and decided, but the
        // stages in between cannot be recorded.
        'admission_applications.status' => ['reviewed', 'interviewed', 'waitlisted'],

        // The legacy assignments module (behind #184's dead code). No screen
        // closes an assignment.
        'assignments.status' => ['closed'],
        'assignment_submissions.status' => ['returned'],

        // Hifz. `hifz_enrollments` is OWNER_ACTIONS item 15 — the vocabulary
        // has no word for "left the Institute" and no screen sets any of these.
        'hifz_assignments.status' => ['completed', 'missed', 'cancelled'],
        'hifz_enrollments.status' => ['paused', 'transferred'],
        'hifz_sessions.status' => ['completed', 'reviewed'],

        // Worse than unwritable: **nothing writes this column at all**, not
        // even its default. `HifzSessionService` creates the row without it,
        // and the live halaqa register is no longer here — F5 (ADR-029) moved
        // sessions to `Courses\Components\Quran`, which writes
        // `QuranSessionRecord` and offering attendance and does accept all
        // four values.
        //
        // Three readers still read this one: `HifzScoringService`'s absent
        // check, `ListHifzSessionRecordsAction`, and the dean dashboard's
        // **Absent Today** card — which is therefore permanently 0.
        //
        // Not fixed here. Pointing those reads at the live source is Qur'an
        // **A.4b**, which STATUS already gates on an operator confirming the
        // dual-write; doing it now would be running a gate that has not run.
        'hifz_session_records.attendance_status' => ['late', 'excused'],

        // An invoice can be drafted, issued and paid. It cannot be cancelled.
        'invoices.status' => ['cancelled'],

        // Course engine: a quiz cannot be closed or archived, and an attempt
        // never reaches `graded`.
        'quizzes.status' => ['closed', 'archived'],
        'quiz_attempts.status' => ['graded'],

        'recitation_practices.status' => ['needs_revision'],

        // A registration flow is started and abandoned; nothing marks one
        // finished. The `resume` link (OWNER_ACTIONS item 14) reads this table.
        'registration_flows.status' => ['completed'],
    ];

    $sources = statusGateSources();
    $unwritable = [];
    $fixed = [];

    foreach (collect(Schema::getTables())->pluck('name')->unique() as $table) {
        $model = Str::studly(Str::singular($table));

        foreach (Schema::getColumns($table) as $column) {
            if (($column['type_name'] ?? '') !== 'enum') {
                continue;
            }

            if ($column['name'] !== 'status' && ! str_ends_with($column['name'], '_status')) {
                continue;
            }

            preg_match_all("/'([^']*)'/", (string) $column['type'], $matches);
            $default = trim((string) ($column['default'] ?? ''), "'");
            $key = $table.'.'.$column['name'];

            // Only the files that mention this table or its model, so a value
            // like `completed` occurring all over the application cannot vouch
            // for a column that never uses it.
            $scope = '';
            foreach ($sources as $source) {
                if (str_contains($source, $table) || preg_match('/\b'.preg_quote($model, '/').'\b/', $source)) {
                    $scope .= "\n".$source;
                }
            }

            foreach ($matches[1] as $value) {
                if ($value === $default) {
                    continue;
                }

                $named = preg_match('/\b'.preg_quote($value, '/').'\b/', $scope)
                    || preg_match('/::'.preg_quote(Str::studly($value), '/').'\b/', $scope);

                $baselined = in_array($value, $baseline[$key] ?? [], true);

                if (! $named && ! $baselined) {
                    $unwritable[] = $key.' → '.$value;
                }

                if ($named && $baselined) {
                    $fixed[] = $key.' → '.$value;
                }
            }
        }
    }

    sort($unwritable);
    sort($fixed);

    expect($unwritable)->toBeEmpty(
        'These status values are allowed by the schema and named nowhere in the code that touches their table, '
        ."so nothing can write them:\n  ".implode("\n  ", $unwritable)
        ."\n\nA column whose other values are unreachable is a filter guarding a constant. Either build the path "
        .'that sets the value, or add it to this test\'s baseline with a reason saying what is missing.'
    );

    expect($fixed)->toBeEmpty(
        "These are now reachable — delete them from the baseline:\n  ".implode("\n  ", $fixed)
        ."\n\nThat is the direction this list is meant to move, and a baseline that keeps stale entries stops "
        .'being read.'
    );
});

/**
 * Every source file that could name a status value, comments removed.
 *
 * Blade and JSX count: a value is often written by a form whose `<option>`
 * list is the only place it appears.
 */
function statusGateSources(): array
{
    static $sources = null;

    if ($sources !== null) {
        return $sources;
    }

    $sources = [];

    foreach (['app', 'resources/views', 'resources/js'] as $directory) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path($directory), FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'jsx', 'js'], true)) {
                continue;
            }

            $sources[] = statusGateStripComments((string) file_get_contents($file->getPathname()));
        }
    }

    return $sources;
}

/**
 * Comments do not make a value writable.
 *
 * Without this, the gate is silenced by writing a sentence about the value —
 * including the sentence explaining why it cannot be written.
 */
function statusGateStripComments(string $source): string
{
    $source = preg_replace('!/\*.*?\*/!s', ' ', $source) ?? $source;
    $source = preg_replace('/^\s*\/\/.*$/m', ' ', $source) ?? $source;

    // `#[Attribute]` is not a comment.
    return preg_replace('/^\s*#(?!\[).*$/m', ' ', $source) ?? $source;
}
