<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('deletes student_guardians whose guardian users are wiped', function () {
    $admin = actingPeopleAdmin();
    $guardian = User::factory()->create(['name' => 'Doomed Guardian']);
    $rs = makeRegistrationStudent(['user_id' => null]);
    attachLegacyGuardian($rs->id, $guardian->id);

    $this->artisan('users:clear-non-admin', ['--force' => true])
        ->assertSuccessful();

    expect(User::query()->find($admin->id))->not->toBeNull()
        ->and(User::query()->find($guardian->id))->toBeNull()
        ->and(DB::table('student_guardians')->count())->toBe(0)
        ->and(DB::table('registration_students')->where('id', $rs->id)->exists())->toBeTrue();
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
