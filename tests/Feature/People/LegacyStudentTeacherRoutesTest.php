<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The legacy `/students` and `/teachers` screens were guarded by `auth` alone.
 *
 * The modern `people.*` equivalents require
 * `role:super_admin|admin|headmaster|supervisor`. These duplicates required
 * nothing but a session, so **any signed-in account — a parent, a pupil —
 * could list, create, edit and delete students and teachers**, including
 * creating user accounts with a password of their choosing.
 *
 * These tests are about who is refused. The screens themselves are legacy Blade
 * and unchanged.
 */
function signedInWithRole(?string $role = null): User
{
    $user = User::factory()->create();

    if ($role !== null) {
        Role::findOrCreate($role, 'web');
        $user->assignRole($role);
    }

    return $user;
}

it('refuses a signed-in account with no role', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(signedInWithRole())
        ->get('/students')
        ->assertForbidden();
});

it('refuses a parent', function () {
    // The account most likely to have one: a parent reading their child's
    // portal could previously open the whole student register.
    $this->withoutLocalizationMiddleware()
        ->actingAs(signedInWithRole('parent'))
        ->get('/students')
        ->assertForbidden();
});

it('refuses a student', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(signedInWithRole('student'))
        ->get('/students')
        ->assertForbidden();
});

it('refuses a teacher, who has their own screens', function () {
    $this->withoutLocalizationMiddleware()
        ->actingAs(signedInWithRole('teacher'))
        ->get('/teachers')
        ->assertForbidden();
});

it('refuses a parent creating a student and a user account', function () {
    // The worst of it: `store` creates a User with a caller-supplied password.
    $this->withoutLocalizationMiddleware()
        ->actingAs(signedInWithRole('parent'))
        ->post('/students', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'date_of_birth' => '2015-01-01',
            'gender' => 'male',
        ])
        ->assertForbidden();

    expect(User::query()->where('email', 'intruder@example.com')->exists())->toBeFalse();
});

it('refuses a parent deleting a student', function () {
    $student = makeStudent();

    $this->withoutLocalizationMiddleware()
        ->actingAs(signedInWithRole('parent'))
        ->delete('/students/'.$student->id)
        ->assertForbidden();

    expect($student->fresh())->not->toBeNull();
});

it('still admits the roles that run the school', function () {
    foreach (['super_admin', 'admin', 'headmaster', 'supervisor'] as $role) {
        $this->withoutLocalizationMiddleware()
            ->actingAs(signedInWithRole($role))
            ->get('/students')
            ->assertOk();
    }
});

it('keeps anonymous visitors out', function () {
    $this->withoutLocalizationMiddleware()
        ->get('/students')
        ->assertRedirect();
});
