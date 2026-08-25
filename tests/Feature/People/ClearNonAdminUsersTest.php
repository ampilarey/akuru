<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Identity\Models\User;
use App\Domains\People\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('deletes student_guardians whose guardian users are wiped', function () {
    $admin = actingPeopleAdmin();
    $guardian = User::factory()->create(['name' => 'Doomed Guardian']);
    $rs = makeRegistrationStudent(['user_id' => null]);
    attachLegacyGuardian($rs->id, $guardian->id);
    DB::table('course_enrollments')->insert([
        'student_id' => $rs->id,
        'course_id' => Course::factory()->create()->id,
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('users:clear-non-admin', ['--force' => true])
        ->assertSuccessful();

    expect(User::query()->find($admin->id))->not->toBeNull()
        ->and(User::query()->find($guardian->id))->toBeNull()
        ->and(DB::table('student_guardians')->count())->toBe(0)
        ->and(DB::table('registration_students')->where('id', $rs->id)->exists())->toBeFalse()
        ->and(DB::table('course_enrollments')->where('student_id', $rs->id)->exists())->toBeFalse();
});

it('deletes student_guardians for registration students whose users are wiped', function () {
    actingPeopleAdmin();
    $studentUser = User::factory()->create();
    $guardian = User::factory()->create();
    $rs = makeRegistrationStudent(['user_id' => $studentUser->id]);
    attachLegacyGuardian($rs->id, $guardian->id);

    $this->artisan('users:clear-non-admin', ['--force' => true])
        ->assertSuccessful();

    expect(DB::table('student_guardians')->where('student_id', $rs->id)->exists())->toBeFalse()
        ->and(DB::table('registration_students')->where('id', $rs->id)->exists())->toBeFalse()
        ->and(User::query()->find($studentUser->id))->toBeNull()
        ->and(User::query()->find($guardian->id))->toBeNull();
});

it('keeps student_guardians that still point at surviving admin users', function () {
    $admin = actingPeopleAdmin();
    $rs = makeRegistrationStudent(['user_id' => $admin->id]);
    attachLegacyGuardian($rs->id, $admin->id);

    $this->artisan('users:clear-non-admin', ['--force' => true])
        ->assertSuccessful();

    expect(DB::table('student_guardians')->count())->toBe(1)
        ->and((int) DB::table('student_guardians')->value('guardian_user_id'))->toBe($admin->id)
        ->and((int) DB::table('student_guardians')->value('student_id'))->toBe($rs->id)
        ->and(User::query()->find($admin->id))->not->toBeNull();
});

it('leaves no student_guardians.guardian_user_id pointing at deleted users', function () {
    actingPeopleAdmin();
    $keptGuardian = actingPeopleAdmin();
    $doomedGuardian = User::factory()->create();
    $keptRs = makeRegistrationStudent(['user_id' => $keptGuardian->id]);
    $doomedRs = makeRegistrationStudent(['user_id' => User::factory()->create()->id]);
    attachLegacyGuardian($keptRs->id, $keptGuardian->id);
    attachLegacyGuardian($doomedRs->id, $doomedGuardian->id);

    $this->artisan('users:clear-non-admin', ['--force' => true])
        ->assertSuccessful();

    $orphans = DB::table('student_guardians')
        ->whereNotIn('guardian_user_id', User::query()->pluck('id'))
        ->count();

    expect($orphans)->toBe(0)
        ->and(DB::table('student_guardians')->count())->toBe(1)
        ->and((int) DB::table('student_guardians')->value('guardian_user_id'))->toBe($keptGuardian->id);
});

it('aborts when no admin or super_admin users exist', function () {
    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('super_admin', 'web');
    User::factory()->create();

    $this->artisan('users:clear-non-admin', ['--force' => true])
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('includes registration_students with NULL user_id in the wipe instead of leaving them as whereNotIn survivors', function () {
    actingPeopleAdmin();
    $guardian = User::factory()->create();
    $rs = makeRegistrationStudent(['user_id' => null]);
    attachLegacyGuardian($rs->id, $guardian->id);

    $this->artisan('users:clear-non-admin', ['--force' => true])
        ->assertSuccessful();

    expect(DB::table('registration_students')->where('id', $rs->id)->exists())->toBeFalse()
        ->and(DB::table('student_guardians')->count())->toBe(0)
        ->and(User::query()->find($guardian->id))->toBeNull();
});

it('deletes guardian_student rows for wiped parent profiles and unified students', function () {
    actingPeopleAdmin();
    $parent = makeGuardian();
    $student = makeStudent(['user_id' => User::factory()->create()->id]);
    DB::table('guardian_student')->insert([
        'guardian_id' => $parent->id,
        'student_id' => $student->id,
        'relationship' => 'father',
        'is_primary' => true,
        'can_pickup' => true,
        'financial_responsible' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('users:clear-non-admin', ['--force' => true])
        ->assertSuccessful();

    expect(DB::table('guardian_student')->count())->toBe(0)
        ->and(DB::table('parent_guardians')->where('id', $parent->id)->exists())->toBeFalse();
});

it('leaves no orphaned student_guardians or guardian_student rows', function () {
    actingPeopleAdmin();
    $guardian = User::factory()->create();
    $rs = makeRegistrationStudent(['user_id' => null]);
    attachLegacyGuardian($rs->id, $guardian->id);
    $parent = makeGuardian();
    $student = makeStudent();
    DB::table('guardian_student')->insert([
        'guardian_id' => $parent->id,
        'student_id' => $student->id,
        'relationship' => 'mother',
        'is_primary' => false,
        'can_pickup' => true,
        'financial_responsible' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('users:clear-non-admin', ['--force' => true])
        ->assertSuccessful();

    $liveUserIds = User::query()->pluck('id');
    $liveGuardianIds = DB::table('parent_guardians')->pluck('id');
    $liveStudentIds = Student::query()->pluck('id');
    $liveRsIds = DB::table('registration_students')->pluck('id');

    $orphanLegacyGuardians = DB::table('student_guardians')->get()->filter(function ($row) use ($liveUserIds, $liveRsIds) {
        return ! $liveUserIds->contains((int) $row->guardian_user_id)
            || ! $liveRsIds->contains((int) $row->student_id);
    });
    $orphanUnifiedPivots = DB::table('guardian_student')->get()->filter(function ($row) use ($liveGuardianIds, $liveStudentIds) {
        return ! $liveGuardianIds->contains((int) $row->guardian_id)
            || ! $liveStudentIds->contains((int) $row->student_id);
    });

    expect($orphanLegacyGuardians)->toBeEmpty()
        ->and($orphanUnifiedPivots)->toBeEmpty()
        ->and(DB::table('student_guardians')->count())->toBe(0)
        ->and(DB::table('guardian_student')->count())->toBe(0);
});
