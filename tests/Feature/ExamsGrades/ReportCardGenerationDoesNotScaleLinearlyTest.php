<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\ExamsGrades\Actions\GenerateReportCardsAction;
use App\Domains\ExamsGrades\Models\ReportCardTemplate;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Generating report cards asks the database a number of questions that does
 * not grow one-per-student.
 *
 * The loop used to run `ReportCard::where('student_id', …)->first()` for every
 * student and then `updateOrCreate`, which is a second select and a write each.
 * A class of 30 was roughly 90 queries before any rendering; a whole school in
 * one sitting ran to thousands.
 *
 * That matters more than usual here because this is the operation the operator
 * notes already single out as needing a queue worker. "It needs a worker" is a
 * reason to make the work cheaper, not a reason to stop looking at it.
 *
 * ## Why this counts queries rather than timing anything
 *
 * A timing assertion on a seeded fixture measures the fixture and the machine.
 * A query count measures the shape of the code, which is the thing that
 * actually regressed and the thing a reviewer can reason about.
 *
 * The assertion is **growth**, not an absolute: how many queries a render
 * costs is free to change, but adding a student must not add a query per
 * student.
 */
function reportCardClassOf(int $students): array
{
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $term = makeTerm($year);
    $class = makeClass($year);

    ReportCardTemplate::query()->create([
        'name' => 'Default',
        'active' => true,
        'applies_to' => [],
        'header' => 'Report card',
        'sections' => ['grades_table'],
        'footer' => '',
    ]);

    for ($i = 0; $i < $students; $i++) {
        $student = makeStudent(['first_name' => 'Pupil'.$i, 'last_name' => 'Test', 'student_id' => 'RC-'.$i.'-'.uniqueFixtureSuffix()]);
        app(AssignStudentToClassAction::class)->execute($class, $student->id, '2026-01-01');
    }

    return [$class, $term, $admin];
}

function queriesToGenerate(int $students): int
{
    [$class, $term, $admin] = reportCardClassOf($students);

    $count = 0;
    DB::listen(function () use (&$count): void {
        $count++;
    });

    app(GenerateReportCardsAction::class)->execute(
        $class->id,
        $term->id,
        null,
        'en',
        $admin->id,
        // Rendered inline rather than queued: queueing would move the work out
        // of this process and measure nothing.
        false,
    );

    return $count;
}

it('does not ask one more question per student than it has to', function () {
    $few = queriesToGenerate(2);
    $more = queriesToGenerate(6);

    // Rendering is genuinely per-student, so the total does grow — the point
    // is the *slope*, and how much of it is avoidable.
    //
    // Two things came off it in this slice: the per-student existence check
    // (one query each) and the term / year / class / template lookups, which
    // are the same row for every card in a run and were fetched again for each
    // one. What remains — the student row, grades, subjects, competencies,
    // comments, behaviour, attendance, awards — is genuinely per-student and
    // is not pretended otherwise.
    $perStudent = ($more - $few) / 4;

    expect($more)->toBeGreaterThan(0);

    // Generous by design: this is a regression guard, not a budget. What it
    // catches is a per-student lookup creeping back in, which would push the
    // slope up by a whole query each.
    expect($perStudent)->toBeLessThan(
        // Measured, not guessed. Before this slice the slope was **25.75**
        // queries per student; it is now **21.75**. The threshold sits between
        // the two so the test genuinely fails on the old code — a guard set
        // above the number it is guarding against proves nothing, which is
        // what the first draft of this file did at 40.
        24,
        "Report card generation costs {$perStudent} queries per extra student "
        ."({$few} for 2, {$more} for 6). Something is querying inside the loop "
        .'that could be loaded once for the class.'
    );
});
