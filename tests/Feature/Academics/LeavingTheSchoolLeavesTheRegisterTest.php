<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\ListClassRosterAction;
use App\Domains\Academics\Actions\ListStudentsOnActiveRosterAction;
use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\Academics\Models\ClassStudent;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\ChangeStudentStatusAction;
use App\Domains\People\Enums\StudentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A pupil who leaves the Institute comes off the class register.
 *
 * ## The defect
 *
 * `students.status` and `class_student.status` recorded the same fact and
 * nothing reconciled them. `PromoteStudentsAction` moves both together at the
 * end of a year (S1_SPEC §121), but the mid-year path had no equivalent:
 * marking a child **withdrawn** in the student directory left their roster row
 * `active`, and `SaveStudentAction` only closes a placement when the *class*
 * changes, not when the pupil leaves.
 *
 * ## Why that is not cosmetic
 *
 * The register grid defaults every row to **present**. A withdrawn pupil left
 * on it is recorded as having attended a lesson they were not at, every day,
 * until somebody notices — and marking them absent instead sends their guardian
 * an absence SMS about a school the family has left. Neither outcome is visible
 * to the person who marked the child withdrawn, because no screen shows a
 * roster row's pupil status beside it.
 *
 * ## What is asserted here, and what is not
 *
 * The three departure statuses close the placement; `inactive` does not, and
 * that is asserted rather than assumed, because "stopped attending" is exactly
 * the case where a school still needs the child on the register to chase it.
 *
 * Not asserted, because it is not done: **no backfill**. Placements left open
 * by a withdrawal recorded before this shipped stay open.
 */
function aClassWithTwoPupils(): array
{
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $class = makeClass($year);

    $staying = makeStudent(['first_name' => 'Staying']);
    $leaving = makeStudent(['first_name' => 'Leaving']);

    app(AssignStudentToClassAction::class)->execute($class, $staying->id, '2026-01-01');
    app(AssignStudentToClassAction::class)->execute($class, $leaving->id, '2026-01-01');

    return [$class, $staying, $leaving, User::factory()->create()->id];
}

it('takes a withdrawn pupil off the register and leaves the rest of the class alone', function () {
    [$class, $staying, $leaving, $actor] = aClassWithTwoPupils();

    app(ChangeStudentStatusAction::class)->execute(
        $leaving,
        StudentStatus::Withdrawn,
        $actor,
        'Family moved',
        '2026-03-10',
    );

    $roster = app(ListClassRosterAction::class)->execute((int) $class->id);

    expect($roster->pluck('student_id')->all())->toBe([$staying->id]);

    $placement = ClassStudent::query()
        ->where('student_id', $leaving->id)
        ->firstOrFail();

    expect($placement->status)->toBe(ClassStudentStatus::Left)
        // The date the office gave, not today's: a withdrawal recorded late
        // still ended when it ended.
        ->and($placement->left_at?->toDateString())->toBe('2026-03-10');
});

it('closes the placement for every way of leaving', function () {
    foreach ([StudentStatus::Graduated, StudentStatus::Transferred, StudentStatus::Withdrawn] as $departure) {
        [$class, $staying, $leaving, $actor] = aClassWithTwoPupils();

        app(ChangeStudentStatusAction::class)->execute($leaving, $departure, $actor);

        // Asserted as the whole roster rather than `not->toContain($id)`.
        // Pest's `toContain` is **variadic**, so a "message" passed as a second
        // argument is checked as a second needle — which an array of ids never
        // contains, so `not->toContain($id, $message)` passes whatever the
        // roster holds. The first draft of this test did exactly that and
        // survived the revert check while its three neighbours failed.
        expect(app(ListClassRosterAction::class)->execute((int) $class->id)->pluck('student_id')->all())
            ->toBe([$staying->id], "A {$departure->value} pupil is still on the register.");
    }
});

it('keeps an inactive pupil on the register, because that is how a school chases them', function () {
    [$class, , $quiet, $actor] = aClassWithTwoPupils();

    app(ChangeStudentStatusAction::class)->execute($quiet, StudentStatus::Inactive, $actor);

    expect(app(ListClassRosterAction::class)->execute((int) $class->id)->pluck('student_id')->all())
        ->toHaveCount(2)
        ->toContain($quiet->id);
});

it('stops counting a leaver on the active roster', function () {
    [, $staying, $leaving, $actor] = aClassWithTwoPupils();

    expect(app(ListStudentsOnActiveRosterAction::class)->execute([$staying->id, $leaving->id]))
        ->toHaveCount(2);

    app(ChangeStudentStatusAction::class)->execute($leaving, StudentStatus::Withdrawn, $actor);

    expect(app(ListStudentsOnActiveRosterAction::class)->execute([$staying->id, $leaving->id]))
        ->toBe([$staying->id]);
});

it('does not reopen a placement that was already closed', function () {
    [, , $leaving, $actor] = aClassWithTwoPupils();

    app(ChangeStudentStatusAction::class)->execute($leaving, StudentStatus::Withdrawn, $actor, null, '2026-03-10');
    // Re-admitted, then leaves again. The first departure's date must not be
    // overwritten by the second — the listener only touches rows still open.
    app(ChangeStudentStatusAction::class)->execute($leaving, StudentStatus::Withdrawn, $actor, null, '2026-06-01');

    expect(ClassStudent::query()->where('student_id', $leaving->id)->firstOrFail()->left_at?->toDateString())
        ->toBe('2026-03-10');
});
