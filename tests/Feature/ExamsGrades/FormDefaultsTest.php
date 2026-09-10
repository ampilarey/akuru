<?php

use App\Domains\ExamsGrades\Actions\ListExamCatalogAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The exam and report-card forms opened on the wrong year and term.
 *
 * Their defaults were `terms[0]` / `classes[0]` — the first row of a list
 * spanning **every** year — so the form offered "Extra / Term 2 / Arabic
 * Beginners" while the table below showed Pilot Grade 5 A. The screen and the
 * control on it disagreed about what was being worked on.
 *
 * The fix is in the pages, which are React. What can be pinned here is the
 * payload they reason over: every term and class must carry the year it belongs
 * to, or filtering by year is impossible in the first place.
 */
it('tells the forms which year each term and class belongs to', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $other = makeYear(['name' => '2025-2026', 'status' => 'closed']);
    makeTerm($year, 'Term 1');
    makeTerm($other, 'Term 2');
    makeClass($year, 'Grade 5', 'A');
    makeClass($other, 'Grade 4', 'B');

    $catalog = app(ListExamCatalogAction::class)->execute();

    // Without academic_year_id on both, a form cannot scope its dropdowns and
    // the defaults necessarily wander across years.
    expect(collect($catalog['terms'])->every(fn (array $row): bool => ($row['academic_year_id'] ?? null) !== null))
        ->toBeTrue()
        ->and(collect($catalog['classes'])->every(fn (array $row): bool => ($row['academic_year_id'] ?? null) !== null))
        ->toBeTrue();

    $forThisYear = collect($catalog['terms'])->where('academic_year_id', (int) $year->id);

    expect($forThisYear)->toHaveCount(1)
        ->and($forThisYear->first()['name'])->toBe('Term 1');
});

it('marks which year and term are the active ones', function () {
    makeYear(['name' => '2025-2026', 'status' => 'closed']);
    $active = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    makeTerm($active, 'Term 1');

    $catalog = app(ListExamCatalogAction::class)->execute();

    // The forms fall back to the *active* year and term rather than whichever
    // row sorts first, so `status` has to reach them.
    expect(collect($catalog['years'])->firstWhere('status', 'active')['id'])->toBe((int) $active->id)
        ->and(collect($catalog['terms'])->first())->toHaveKey('status');
});
