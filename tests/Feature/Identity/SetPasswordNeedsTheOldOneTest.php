<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * SPEC §44/§45 — *"do not rely only on frontend button hiding"*, *"backend must
 * enforce permissions"*. The same class of defect this repo already has a
 * KNOWN_ISSUES entry for, found in a second place.
 *
 * The dashboard offers **Set a password for easier login** only to an account
 * that has no usable one. The route offered it to everybody, and skipped the
 * `current_password` check that `ProfileController` requires on the app's other
 * password route. One door locked, the other not.
 *
 * What that costs: session access — a stolen cookie, an unlocked shared device,
 * an XSS anywhere in the app — became **permanent account takeover**. The
 * attacker sets a new password without ever knowing the old one, and the owner
 * is locked out of their own account.
 *
 * `force_password_change` is the genuine "no usable password" state.
 * `AccountResolverService` creates OTP-only accounts with a random
 * 40-character hash nobody holds and sets the flag; those accounts cannot
 * supply a current password and must not be asked for one.
 */
function setPasswordUser(bool $mustSetOne): User
{
    return User::factory()->create([
        'password' => Hash::make('correct-horse'),
        'force_password_change' => $mustSetOne,
    ]);
}

it('refuses to change an established password without the current one', function () {
    $user = setPasswordUser(mustSetOne: false);

    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->post('/account/set-password', [
            'password' => 'attacker-chosen',
            'password_confirmation' => 'attacker-chosen',
        ])
        ->assertSessionHasErrors('current_password');

    // The account is still the owner's.
    expect(Hash::check('correct-horse', $user->fresh()->password))->toBeTrue();
});

it('refuses a wrong current password', function () {
    $user = setPasswordUser(mustSetOne: false);

    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->post('/account/set-password', [
            'current_password' => 'not-it',
            'password' => 'attacker-chosen',
            'password_confirmation' => 'attacker-chosen',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('correct-horse', $user->fresh()->password))->toBeTrue();
});

it('lets the owner change their own password with the current one', function () {
    $user = setPasswordUser(mustSetOne: false);

    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->post('/account/set-password', [
            'current_password' => 'correct-horse',
            'password' => 'a-better-one',
            'password_confirmation' => 'a-better-one',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('a-better-one', $user->fresh()->password))->toBeTrue();
});

it('still lets an OTP-only account set its first password without one', function () {
    // The flow the screen exists for. Demanding a current password here would
    // lock out exactly the people it is meant to help: their stored hash is 40
    // random characters nobody has ever seen.
    $user = setPasswordUser(mustSetOne: true);

    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->post('/account/set-password', [
            'password' => 'my-first-password',
            'password_confirmation' => 'my-first-password',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('my-first-password', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->force_password_change)->toBeFalse();
});

it('asks for the current password on the form exactly when it will require it', function () {
    // Otherwise the screen is uncompletable: validation demands a field the
    // view never rendered, and the user sees an error they cannot act on.
    $this->withoutLocalizationMiddleware()->actingAs(setPasswordUser(mustSetOne: false))
        ->get('/account/set-password')
        ->assertOk()
        ->assertSee('name="current_password"', false);

    $this->withoutLocalizationMiddleware()->actingAs(setPasswordUser(mustSetOne: true))
        ->get('/account/set-password')
        ->assertOk()
        ->assertDontSee('name="current_password"', false);
});

it('shows the set-password prompt to an account that needs one', function () {
    // The second half of the same root cause. `$hasPassword` was
    // `! empty($user->password)`, and `users.password` is NOT NULL with OTP
    // accounts carrying a random hash — so it was **always true** and this
    // banner never rendered for anybody. The feature was unreachable through
    // its own entry point.
    $this->withoutLocalizationMiddleware()->actingAs(setPasswordUser(mustSetOne: true))
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Set a password');
});

it('does not nag an account that already has a password', function () {
    $this->withoutLocalizationMiddleware()->actingAs(setPasswordUser(mustSetOne: false))
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee('Set a password for easier login');
});
