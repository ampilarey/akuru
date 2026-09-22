<?php

use App\Domains\Identity\Models\User;
use App\Domains\People\Models\StaffProfile;
use App\Domains\People\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The last of S1's DoD line "legacy Blade screens removed". The Blade
 * `/teachers` form outlived the React staff directory for one reason: it was
 * the only screen that could create a teacher's *account* — user, password,
 * `teacher` role, `teachers` row — while `people.staff.store` took an
 * existing `user_id`. This file pins that the staff form now does that job,
 * and that the old screen is gone.
 */
beforeEach(function () {
    foreach (['admin', 'teacher', 'supervisor', 'headmaster'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    // The `teachers` row needs the one school row (ADR-001); the app's own
    // seeder provides it rather than a hand-built one with guessed columns.
    $this->seed(\Database\Seeders\SchoolSeeder::class);
});

it('no longer registers the Blade teacher CRUD routes', function () {
    foreach (['teachers.create', 'teachers.store', 'teachers.edit', 'teachers.update', 'teachers.destroy'] as $name) {
        expect(Route::has($name))->toBeFalse("Route [{$name}] should be gone.");
    }

    $this->withoutLocalizationMiddleware()
        ->actingAs(actingPeopleAdmin())
        ->post('/teachers', ['name' => 'Ghost'])
        ->assertStatus(405);
});

it('sends the old teacher links to the React staff screens', function () {
    $admin = actingPeopleAdmin();
    $staff = makeStaffProfile();
    $teacher = Teacher::query()->create([
        'user_id' => $staff->user_id,
        'school_id' => \Illuminate\Support\Facades\DB::table('schools')->value('id'),
        'teacher_id' => 'T-RETIRED-1',
        'first_name' => 'Old', 'last_name' => 'Link',
        'date_of_birth' => '1985-01-01', 'gender' => 'female',
        'phone' => '', 'address' => '', 'email' => '',
        'qualification' => 'BA', 'specialization' => 'General',
        'joining_date' => '2020-01-01', 'status' => 'active',
    ]);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get('/teachers')->assertRedirect('/people/staff');

    // Keyed on the `teachers` row, lands on the staff profile of the same person.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get('/teachers/'.$teacher->id)->assertRedirect('/people/staff/'.$staff->id);
});

it('creates the account, the role and the teachers row from the staff form', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(actingPeopleAdmin())
        ->post('/people/staff', [
            'first_name' => 'Mariyam',
            'last_name' => 'Teacher',
            'email' => 'mariyam.teacher@example.com',
            'password' => 'chalk-and-talk-9',
            'role' => 'teacher',
            'employment_type' => 'full_time',
            'status' => 'active',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $user = User::query()->where('email', 'mariyam.teacher@example.com')->firstOrFail();
    $profile = StaffProfile::query()->where('user_id', $user->id)->firstOrFail();

    expect($user->hasRole('teacher'))->toBeTrue()
        // The password the admin typed is the one that works — parity with
        // what the Blade screen did, and what every existing teacher has.
        ->and(Hash::check('chalk-and-talk-9', $user->password))->toBeTrue()
        ->and($profile->first_name)->toBe('Mariyam')
        // The row the timetable, registers and teacher pickers key on.
        ->and(Teacher::query()->where('user_id', $user->id)->exists())->toBeTrue();
});

it('makes no teachers row for staff who are not teachers', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(actingPeopleAdmin())
        ->post('/people/staff', [
            'first_name' => 'Ahmed',
            'last_name' => 'Supervisor',
            'email' => 'ahmed.supervisor@example.com',
            'password' => 'watching-closely-1',
            'role' => 'supervisor',
            'employment_type' => 'full_time',
            'status' => 'active',
        ])
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'ahmed.supervisor@example.com')->firstOrFail();
    expect($user->hasRole('supervisor'))->toBeTrue()
        ->and(Teacher::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('refuses a role that is not staff, a used email, and a short password', function () {
    $admin = actingPeopleAdmin();
    $taken = User::factory()->create();
    $base = ['first_name' => 'X', 'last_name' => 'Y', 'employment_type' => 'full_time', 'status' => 'active'];

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post('/people/staff', $base + ['email' => 'p@example.com', 'password' => 'long-enough-1', 'role' => 'parent'])
        ->assertSessionHasErrors('role');

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post('/people/staff', $base + ['email' => $taken->email, 'password' => 'long-enough-1', 'role' => 'teacher'])
        ->assertSessionHasErrors('email');

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post('/people/staff', $base + ['email' => 'q@example.com', 'password' => 'short', 'role' => 'teacher'])
        ->assertSessionHasErrors('password');

    // Neither an account nor a profile for any of them.
    expect(User::query()->whereIn('email', ['p@example.com', 'q@example.com'])->exists())->toBeFalse()
        ->and(StaffProfile::query()->where('first_name', 'X')->exists())->toBeFalse();
});

it('still links an existing account when one is given', function () {
    $existing = User::factory()->create();

    $this->withoutLocalizationMiddleware()
        ->actingAs(actingPeopleAdmin())
        ->post('/people/staff', [
            'user_id' => $existing->id,
            'first_name' => 'Linked',
            'last_name' => 'Person',
            'employment_type' => 'part_time',
            'status' => 'active',
        ])
        ->assertSessionHasNoErrors();

    expect(StaffProfile::query()->where('user_id', $existing->id)->exists())->toBeTrue()
        ->and(User::query()->count())->toBe(2); // admin + existing, nobody new
});
