<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Identity\Models\User;
use App\Domains\People\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * `users:clear-non-admin` wipes every account but admins'. Since Deploy 3
 * (slice 3) registrations live on `students` and `guardian_student`, and the
 * old `registration_students` / `student_guardians` rows sit in their
 * `archived_` tables; the wipe covers both.
 */
function clearLink(int $guardianId, int $studentId, string $relationship = 'mother'): void
{
    DB::table('guardian_student')->insert([
        'guardian_id' => $guardianId,
        'student_id' => $studentId,
        'relationship' => $relationship,
        'is_primary' => true,
        'can_pickup' => true,
        'financial_responsible' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function clearChild(): Student
{
    return Student::query()->create([
        'user_id' => null,
        'first_name' => 'Registered',
        'last_name' => 'Child',
        'date_of_birth' => now()->subYears(9)->toDateString(),
        'gender' => 'female',
    ]);
}

it('wipes a child a wiped parent registered, with their enrolment and link', function () {
    $admin = actingPeopleAdmin();
    $parent = makeGuardian();
    $child = clearChild();
    clearLink($parent->id, $child->id);
    DB::table('course_enrollments')->insert([
        'unified_student_id' => $child->id,
        'course_id' => Course::factory()->create()->id,
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('users:clear-non-admin', ['--force' => true])->assertSuccessful();

    expect(User::query()->find($admin->id))->not->toBeNull()
        ->and(User::query()->find($parent->user_id))->toBeNull()
        ->and(DB::table('guardian_student')->count())->toBe(0)
        ->and(DB::table('course_enrollments')->where('unified_student_id', $child->id)->exists())->toBeFalse();
});

it('wipes an adult registrant\'s enrolments with their account', function () {
    actingPeopleAdmin();
    $student = makeStudent();
    DB::table('course_enrollments')->insert([
        'unified_student_id' => $student->id,
        'course_id' => Course::factory()->create()->id,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('users:clear-non-admin', ['--force' => true])->assertSuccessful();

    expect(User::query()->find($student->user_id))->toBeNull()
        ->and(DB::table('course_enrollments')->where('unified_student_id', $student->id)->exists())->toBeFalse();
});

it('keeps a child who still has a surviving guardian', function () {
    $admin = actingPeopleAdmin();
    $adminGuardian = makeGuardian();
    $adminGuardian->forceFill(['user_id' => $admin->id])->save();
    $doomedParent = makeGuardian();
    $child = clearChild();
    clearLink($adminGuardian->id, $child->id);
    clearLink($doomedParent->id, $child->id, 'father');
    DB::table('course_enrollments')->insert([
        'unified_student_id' => $child->id,
        'course_id' => Course::factory()->create()->id,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('users:clear-non-admin', ['--force' => true])->assertSuccessful();

    expect(DB::table('guardian_student')->count())->toBe(1)
        ->and((int) DB::table('guardian_student')->value('guardian_id'))->toBe($adminGuardian->id)
        ->and(DB::table('course_enrollments')->where('unified_student_id', $child->id)->exists())->toBeTrue();
});

it('aborts when no admin or super_admin users exist', function () {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('super_admin', 'web');
    User::factory()->create();

    $this->artisan('users:clear-non-admin', ['--force' => true])
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('wipes archived registrations of wiped users and guardian-only archived rows', function () {
    $admin = actingPeopleAdmin();
    $doomed = User::factory()->create();

    $ownedId = DB::table('archived_registration_students')->insertGetId([
        'user_id' => $doomed->id, 'first_name' => 'Old', 'last_name' => 'Adult', 'dob' => '1990-01-01',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $childId = DB::table('archived_registration_students')->insertGetId([
        'user_id' => null, 'first_name' => 'Old', 'last_name' => 'Child', 'dob' => '2015-01-01',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $adminsId = DB::table('archived_registration_students')->insertGetId([
        'user_id' => $admin->id, 'first_name' => 'Kept', 'last_name' => 'Admin', 'dob' => '1985-01-01',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('archived_student_guardians')->insert([
        'student_id' => $childId, 'guardian_user_id' => $doomed->id, 'relationship' => 'mother', 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('users:clear-non-admin', ['--force' => true])->assertSuccessful();

    expect(DB::table('archived_registration_students')->pluck('id')->all())->toBe([$adminsId])
        ->and(DB::table('archived_student_guardians')->count())->toBe(0)
        ->and($ownedId)->toBeInt();
});

it('leaves no guardian_student row pointing at a wiped guardian or student', function () {
    actingPeopleAdmin();
    $parent = makeGuardian();
    $student = makeStudent();
    clearLink($parent->id, $student->id);

    $this->artisan('users:clear-non-admin', ['--force' => true])->assertSuccessful();

    $liveGuardianIds = DB::table('parent_guardians')->pluck('id');
    $orphans = DB::table('guardian_student')->get()
        ->filter(fn ($row) => ! $liveGuardianIds->contains((int) $row->guardian_id));

    expect($orphans)->toBeEmpty()
        ->and(DB::table('guardian_student')->count())->toBe(0)
        ->and(DB::table('parent_guardians')->where('id', $parent->id)->exists())->toBeFalse();
});
