<?php

use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\EnsureTeacherRowAction;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * The family cast: a parent with a child, a student who *is* that child, and a
 * teacher who has a `teachers` row.
 *
 * That last part matters. A `teachers` row is not the same thing as the Spatie
 * role `teacher`, and several `teach/*` screens resolve the row rather than the
 * role. A fixture carrying only the role is refused 403 — correctly — and a
 * sweep built on it reports a defect that is really a missing fixture. That
 * happened while writing `PortalScreensDoNotCrashTest`.
 *
 * Shared rather than declared in a test file, for the reason `makeNotice()`
 * already records: a function declared in a test file only exists if that file
 * happens to have been loaded, so two tests cannot rely on it.
 *
 * @return array{parent: User, student: User, teacher: User, child_id: int, class_id: int, year_id: int}
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

    // Phone and address are given explicitly because `EnsureTeacherRowAction`
    // copies them into `teachers`, where both are NOT NULL. That action now
    // falls back to an empty string (#256), so this is belt-and-braces rather
    // than load-bearing — but a teacher with a real phone is the truer fixture.
    $teacherUser = User::factory()->create([
        'name' => 'Portal Teacher', 'phone' => '7770101', 'address' => 'Malé',
    ]);
    $teacherUser->assignRole('teacher');
    app(EnsureTeacherRowAction::class)->execute((int) $teacherUser->id, (int) $class->school_id);

    return [
        'parent' => $parentUser->fresh(),
        'student' => $studentUser->fresh(),
        'teacher' => $teacherUser->fresh(),
        'child_id' => (int) $child->id,
        'class_id' => (int) $class->id,
        'year_id' => (int) $year->id,
    ];
}
