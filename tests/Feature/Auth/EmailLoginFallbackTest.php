<?php

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Email login reads user_contacts, not users.email. Accounts created outside
 * the contact-aware flows (Breeze registration, admin creation, seeders,
 * console recovery) never get a contact row, so they could authenticate
 * exactly once — at registration, via Auth::login — and never again.
 *
 * The fallback must not weaken the OTP gate: the public course-registration
 * flow creates contacts with verified_at = null while verification is
 * pending, and those must stay locked out.
 */
function loginUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'email' => 'someone@akuru.edu.mv',
        'password' => Hash::make('correct-horse'),
        'is_active' => true,
    ], $attributes));
}

it('logs in with a verified email contact', function () {
    $user = loginUser();
    UserContact::create([
        'user_id' => $user->id,
        'type' => 'email',
        'value' => 'someone@akuru.edu.mv',
        'is_primary' => true,
        'verified_at' => now(),
    ]);

    $this->post(route('login'), [
        'identifier' => 'someone@akuru.edu.mv',
        'password' => 'correct-horse',
    ])->assertRedirect();

    expect(auth()->id())->toBe($user->id);
});

it('logs in from users.email when the account has no contact row at all', function () {
    $user = loginUser();

    expect(UserContact::query()->where('user_id', $user->id)->count())->toBe(0);

    $this->post(route('login'), [
        'identifier' => 'someone@akuru.edu.mv',
        'password' => 'correct-horse',
    ])->assertRedirect();

    expect(auth()->id())->toBe($user->id);
});

it('keeps an unverified contact locked out rather than falling back', function () {
    $user = loginUser();
    // The public registration flow's pending-OTP state. users.email matches,
    // so a naive fallback would let this through and bypass verification.
    UserContact::create([
        'user_id' => $user->id,
        'type' => 'email',
        'value' => 'someone@akuru.edu.mv',
        'is_primary' => true,
        'verified_at' => null,
    ]);

    $this->post(route('login'), [
        'identifier' => 'someone@akuru.edu.mv',
        'password' => 'correct-horse',
    ])->assertSessionHasErrors('identifier');

    expect(auth()->check())->toBeFalse();
});

it('matches users.email case-insensitively', function () {
    $user = loginUser(['email' => 'mixed@akuru.edu.mv']);

    $this->post(route('login'), [
        'identifier' => '  MiXeD@Akuru.Edu.MV  ',
        'password' => 'correct-horse',
    ])->assertRedirect();

    expect(auth()->id())->toBe($user->id);
});

it('still rejects a wrong password on the fallback path', function () {
    loginUser();

    $this->post(route('login'), [
        'identifier' => 'someone@akuru.edu.mv',
        'password' => 'wrong',
    ])->assertSessionHasErrors('identifier');

    expect(auth()->check())->toBeFalse();
});

it('still refuses an inactive account on the fallback path', function () {
    loginUser(['is_active' => false]);

    $this->post(route('login'), [
        'identifier' => 'someone@akuru.edu.mv',
        'password' => 'correct-horse',
    ])->assertSessionHasErrors('identifier');

    expect(auth()->check())->toBeFalse();
});

it('lets a freshly registered user log in again after logging out', function () {
    // The whole point: registration writes users.email and no contact row.
    $this->post(route('register'), [
        'name' => 'New Person',
        'email' => 'newperson@akuru.edu.mv',
        'password' => 'correct-horse',
        'password_confirmation' => 'correct-horse',
    ]);

    $this->post(route('logout'));
    expect(auth()->check())->toBeFalse();

    $this->post(route('login'), [
        'identifier' => 'newperson@akuru.edu.mv',
        'password' => 'correct-horse',
    ])->assertRedirect();

    expect(auth()->check())->toBeTrue();
});
