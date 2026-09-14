<?php

use App\Domains\Academics\Actions\ListActiveTeachersAction;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\CountTeachersAction;
use App\Domains\People\Actions\ListClassTeacherOptionsAction;
use App\Domains\People\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Ending somebody's employment reaches the row the school actually reads.
 *
 * ## The defect
 *
 * A member of staff is recorded twice. `staff_profiles` is what the staff
 * directory edits — `active` / `on_leave` / `ended`, with a form, validation
 * and a CSV. `teachers` is the older row that the timetable, the registers and
 * every teacher picker key off.
 *
 * Nothing connected them, and `teachers.status` was **written once**: both
 * `TeacherController::store` and `EnsureTeacherRowAction` hardcode `'active'`
 * and no path in the application ever updated it. So ending an employment left
 * the teacher row `active` for ever.
 *
 * ## Which makes four existing filters inert
 *
 * `ListActiveTeachersAction`, the meeting-slot picker, the teacher-contact list
 * and the staff counter all say `where('status', 'active')` over a column that
 * could not move. They were correct code guarding a constant — which is why
 * nothing failed and nobody noticed.
 *
 * That is also a correction to this session's own #378: its claim that the
 * dashboard's **Teachers** tile "included staff whose employment had ended"
 * was true of the query and not of the data, because no employment could end
 * in that column. The count it ships is still the right one; the reason given
 * for it was overstated, and this slice is what makes it true.
 *
 * ## What is asserted
 *
 * `ended` deactivates and `on_leave` does **not** — asserted, not assumed,
 * because a teacher on leave is exactly the one a school must keep finding in
 * a picker in order to arrange cover for them.
 */
function aTeacherWithAStaffProfile(): array
{
    $teacher = makeTeacherRow();

    $profile = makeStaffProfile([
        'user_id' => $teacher->user_id,
        'first_name' => $teacher->first_name,
        'last_name' => $teacher->last_name,
    ]);

    return [$teacher, $profile];
}

function endEmployment(StaffProfile $profile, string $status = 'ended'): void
{
    $admin = User::factory()->create();
    Role::findOrCreate('super_admin', 'web');
    Permission::findOrCreate('people.manage', 'web');
    $admin->assignRole('super_admin');
    $admin->givePermissionTo('people.manage');

    test()->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->put(route('people.staff.update', $profile), [
            'user_id' => $profile->user_id,
            'first_name' => $profile->first_name,
            'last_name' => $profile->last_name,
            'employment_type' => 'full_time',
            'status' => $status,
        ])
        ->assertRedirect();
}

it('marks the teacher row terminated when employment ends', function () {
    [$teacher, $profile] = aTeacherWithAStaffProfile();

    expect($teacher->fresh()->status)->toBe('active');

    endEmployment($profile);

    expect($teacher->fresh()->status)->toBe('terminated');
});

it('leaves a teacher on leave in every picker, because that is how cover is arranged', function () {
    [$teacher, $profile] = aTeacherWithAStaffProfile();

    endEmployment($profile, 'on_leave');

    expect($teacher->fresh()->status)->toBe('active')
        ->and(app(ListActiveTeachersAction::class)->execute()->pluck('id')->all())
        ->toContain($teacher->id);
});

it('puts the teacher row back if the employment is un-ended', function () {
    [$teacher, $profile] = aTeacherWithAStaffProfile();

    endEmployment($profile);
    expect($teacher->fresh()->status)->toBe('terminated');

    endEmployment($profile->refresh(), 'active');

    expect($teacher->fresh()->status)->toBe('active');
});

it('stops offering and counting a teacher whose employment ended', function () {
    [$teacher, $profile] = aTeacherWithAStaffProfile();
    [$staying] = aTeacherWithAStaffProfile();

    expect(app(CountTeachersAction::class)->teaching())->toBe(2);

    endEmployment($profile);

    expect(app(CountTeachersAction::class)->teaching())->toBe(1)
        ->and(app(CountTeachersAction::class)->everEmployed())->toBe(2)
        ->and(app(ListActiveTeachersAction::class)->execute()->pluck('id')->all())->toBe([$staying->id])
        ->and(app(ListClassTeacherOptionsAction::class)->assignable()->pluck('id')->all())
        ->toBe([(int) $staying->user_id]);
});

it('keeps a class that already has that teacher able to still show them', function () {
    [$teacher, $profile] = aTeacherWithAStaffProfile();

    endEmployment($profile);

    $options = app(ListClassTeacherOptionsAction::class);

    // Naming: the leaver is still there, so a class they ran is not blanked.
    expect($options->everyone()->pluck('id')->all())->toContain((int) $teacher->user_id);

    // Choosing: they are gone — unless this class already has them, in which
    // case the picker must keep its own current value or the next save
    // silently clears it.
    expect($options->assignable()->pluck('id')->all())
        ->not->toContain((int) $teacher->user_id);

    expect($options->assignable([$teacher->user_id])->pluck('id')->all())
        ->toContain((int) $teacher->user_id);
});
