<?php

use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\EnsureTeacherRowAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Every family- and teacher-facing screen loads for the person it is for.
 *
 * `StaffScreensDoNotCrashTest` takes every screen that is not family-facing.
 * This takes the other half — `portal/`, `learn` and `teach/`, the ones parents
 * actually open — and walks them as the people they are built for.
 *
 * **Walked as the real audience, never as a super_admin.** An administrator
 * holding every permission would sail through screens whose scoping is broken
 * for the person they are built for, which is the failure worth catching here:
 * a parent's page that works only because the tester could see everything.
 *
 * The assertion is narrow on purpose — **no 5xx** — matching its sibling. 403
 * is allowed: a parent opening `portal/overview` or `teach/assignments` should
 * be refused, and several of these screens are staff-only by design.
 *
 * The screen list comes from `familyScreens()` in `ScreenCensusHelpers`. It
 * used to be built here from the prefixes `['portal/', 'learn', 'teach']`, and
 * the last of those was a bug: as a bare string it also matches `teachers/`,
 * so the staff teacher CRUD was being swept as a family screen — and, since no
 * guard's prefix list contained `teachers` either, it was swept *only* as a
 * family screen, by three roles who are all correctly refused it. The census
 * matches on segment boundaries.
 */

/**
 * A parent with a child, a student who *is* that child, and a teacher who has
 * a `teachers` row.
 *
 * That last part matters: `teachers` row ≠ Spatie role `teacher`, and several
 * `teach/*` screens resolve the row rather than the role. A fixture with only
 * the role is refused 403 — correctly — and a sweep built on it would report
 * a defect that is really a missing fixture. That happened while writing this.
 */
function portalCast(): array
{
    foreach (['parent', 'student', 'teacher'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $year = makeYear(['name' => 'Portal year', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);

    $studentUser = User::factory()->create(['name' => 'Portal Student']);
    $studentUser->assignRole('student');

    $child = makeStudent(['first_name' => 'Portal', 'last_name' => 'Child']);
    DB::table('students')->where('id', $child->id)->update(['user_id' => $studentUser->id]);

    $parentUser = User::factory()->create(['name' => 'Portal Parent']);
    $parentUser->assignRole('parent');

    $guardianId = DB::table('parent_guardians')->insertGetId([
        'user_id' => $parentUser->id, 'first_name' => 'Portal', 'last_name' => 'Parent',
        'phone' => '7770100', 'email' => 'portal.parent@example.test',
        'address' => 'Malé', 'relationship' => 'mother',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('guardian_student')->insert([
        'guardian_id' => $guardianId, 'student_id' => $child->id,
        'relationship' => 'mother', 'is_primary' => true, 'can_pickup' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Phone and address matter: `EnsureTeacherRowAction` copies `users.phone`
    // and `users.address` into `teachers`, where both are NOT NULL, so a user
    // missing either makes it throw. Only `UserSeeder` calls that action
    // today, so this is a fragility rather than a reachable defect — noted in
    // STATUS, not fixed here (rule 1).
    $teacherUser = User::factory()->create([
        'name' => 'Portal Teacher', 'phone' => '7770101', 'address' => 'Malé',
    ]);
    $teacherUser->assignRole('teacher');
    app(EnsureTeacherRowAction::class)->execute((int) $teacherUser->id, (int) $class->school_id);

    return [
        'parent' => $parentUser->fresh(),
        'student' => $studentUser->fresh(),
        'teacher' => $teacherUser->fresh(),
    ];
}

it('loads every family and teacher screen without a server error', function () {
    $cast = portalCast();
    $screens = familyScreens();

    expect(count($screens))->toBeGreaterThan(30);

    $crashed = [];

    foreach ($cast as $who => $user) {
        foreach ($screens as [$name, $uri]) {
            try {
                $response = $this->withoutLocalizationMiddleware()
                    ->actingAs($user->fresh())
                    ->get('/'.$uri);

                if ($response->getStatusCode() >= 500) {
                    $crashed[] = sprintf('%s → %s (%s) → %d', $who, $uri, $name, $response->getStatusCode());
                }
            } catch (Throwable $e) {
                $crashed[] = sprintf('%s → %s threw %s: %s', $who, $uri, $e::class, $e->getMessage());
            }
        }
    }

    expect($crashed)->toBeEmpty(
        count($crashed)." family/teacher screen(s) return a server error:\n".implode("\n", $crashed)
    );
});
