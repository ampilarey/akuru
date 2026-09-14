<?php

use App\Domains\Courses\Actions\ComposeCatalogReportsAction;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\ChangeStudentStatusAction;
use App\Domains\People\Actions\CountStudentsAction;
use App\Domains\People\Actions\CountTeachersAction;
use App\Domains\People\Enums\StudentStatus;
use App\Domains\Website\Actions\ComposeHomepageTrustAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * "Students" is one word for two numbers, and this pins which screen means
 * which.
 *
 * ## The defect (KNOWN_ISSUES #22, and more than it was filed as)
 *
 * The supervisor dashboard's **Students** and **Teachers** tiles ran
 * `Student::count()` and `Teacher::count()` — every row, whatever its status.
 * A pupil who graduated in July was still one of the school's students in
 * September; a teacher who left was still on the staff. The §33 catalog report
 * had the same call under a comment reading *"this is the roll of the
 * institute"*, so the label and the query disagreed in writing.
 *
 * ## Why not simply filter everywhere
 *
 * Because the homepage's **students taught** is a genuinely different
 * question. It is a cumulative claim about the Institute's history, and
 * counting only today's roll would undersell every cohort that has finished.
 * One of these numbers had to keep the old behaviour, so the distinction is
 * named rather than applied uniformly.
 *
 * Each test below builds the same fixture — one pupil on the roll and three
 * who are not: a graduate, a leaver and an applicant who has not started — and
 * asserts that the two questions give *different* answers. A test that only
 * checked the roll would pass just as happily if `everEnrolled()` had been
 * filtered too, which would silently shrink the homepage's claim.
 */
function aRollWithOneLeaver(): void
{
    // `status` is not fillable — it moves through ChangeStudentStatusAction so
    // that every change leaves a history row. The fixture goes the same way
    // rather than writing the column directly, which also means these students
    // reach the counters exactly as real ones do.
    $changer = app(ChangeStudentStatusAction::class);
    $actor = User::factory()->create()->id;

    makeStudent(['first_name' => 'Here']);

    foreach ([StudentStatus::Graduated, StudentStatus::Withdrawn, StudentStatus::Prospective] as $gone) {
        $changer->execute(makeStudent(['first_name' => $gone->value]), $gone, $actor);
    }
}

it('counts the roll without the people who have left it', function () {
    aRollWithOneLeaver();

    expect(app(CountStudentsAction::class)->onTheRoll())->toBe(1)
        ->and(app(CountStudentsAction::class)->everEnrolled())->toBe(4);
});

it('counts teaching staff without the ones whose employment ended', function () {
    makeTeacherRow();
    $left = makeTeacherRow();
    $left->update(['status' => 'terminated']);
    $onLeave = makeTeacherRow();
    $onLeave->update(['status' => 'inactive']);

    expect(app(CountTeachersAction::class)->teaching())->toBe(1)
        ->and(app(CountTeachersAction::class)->everEmployed())->toBe(3);
});

it('shows the supervisor the roll rather than the row count', function () {
    aRollWithOneLeaver();
    makeTeacherRow();
    makeTeacherRow()->update(['status' => 'terminated']);

    Role::findOrCreate('supervisor', 'web');
    $user = User::factory()->create();
    $user->assignRole('supervisor');

    $response = $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewIs('dashboard.supervisor');

    $stats = $response->viewData('stats');

    expect($stats['students_on_roll'])->toBe(1)
        ->and($stats['teachers_teaching'])->toBe(1);

    // And the labels say which number it is, so the tile cannot be read as the
    // other one. This is half the fix: the old tiles were headed "Students"
    // and "Teachers", which is true of either count.
    $response->assertSee('Students on the roll')
        ->assertSee('Teachers on staff');
});

it('reports the roll, not every record, in the catalog totals', function () {
    aRollWithOneLeaver();

    $totals = app(ComposeCatalogReportsAction::class)->execute([])['totals'];

    expect($totals['students'])->toBe(1);
});

it('keeps counting the students the Institute has ever taught on the homepage', function () {
    aRollWithOneLeaver();

    // The display floor exists so a tiny computed number is held back rather
    // than undersold; set it low enough that the count itself is what is being
    // tested here.
    app(\App\Domains\Settings\Actions\SetSettingAction::class)->execute('trust.students_min_display', '1');

    $trust = app(ComposeHomepageTrustAction::class)->execute('en');

    expect($trust['students_taught'])->toBe(4);
});
