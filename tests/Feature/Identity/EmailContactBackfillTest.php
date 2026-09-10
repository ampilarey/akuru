<?php

use App\Domains\Identity\Actions\CreateUserAction;
use App\Domains\Identity\Actions\EnsureVerifiedEmailContactAction;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Password reset by email could not find staff-created accounts.
 *
 * `OtpPasswordResetController` resolves a person through `user_contacts`, not
 * `users.email`. Accounts created through the People screens had no contact
 * row, so the reset flow found nothing — and because it deliberately does not
 * reveal whether an account exists, the person was told a code had been sent
 * and never received one. No error, no support signal.
 *
 * `EnsureVerifiedEmailContactAction` existed to prevent exactly this and was
 * called by nothing.
 */
it('gives a new account the contact row password reset looks for', function () {
    $created = app(CreateUserAction::class)->execute('Fathimath', 'fathimath@example.com');

    $contact = UserContact::query()->where('type', 'email')->sole();

    expect((int) $contact->user_id)->toBe($created['id'])
        ->and($contact->value)->toBe('fathimath@example.com')
        ->and($contact->verified_at)->not->toBeNull();
});

it('is idempotent for an account that already has one', function () {
    $user = User::factory()->create(['email' => 'once@example.com']);
    $action = app(EnsureVerifiedEmailContactAction::class);

    $first = $action->execute($user);
    $second = $action->execute($user);

    expect(UserContact::query()->where('type', 'email')->count())->toBe(1)
        ->and((int) $second->id)->toBe((int) $first->id);
});

it('refuses to claim an address that belongs to somebody else', function () {
    // `users.email` is itself unique, so this cannot be built with two accounts
    // sharing a login address. It is reachable the way it happens in practice:
    // somebody registers a *secondary* contact, and a later account is created
    // with that address as its login.
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    UserContact::query()->create([
        'user_id' => $owner->id,
        'type' => 'email',
        'value' => 'shared@example.com',
        'is_primary' => false,
        'verified_at' => now(),
    ]);

    $other = User::factory()->create(['email' => 'shared@example.com']);

    // `user_contacts(type, value)` is globally unique. The previous
    // firstOrCreate would have handed back the owner's row and the caller would
    // have believed it ensured a contact — pointing one person's reset flow at
    // another person's account.
    expect(app(EnsureVerifiedEmailContactAction::class)->execute($other))->toBeNull()
        ->and(UserContact::query()->where('value', 'shared@example.com')->count())->toBe(1)
        ->and((int) UserContact::query()->where('value', 'shared@example.com')->sole()->user_id)
        ->toBe((int) $owner->id);
});

it('ignores an account with no usable address', function () {
    $action = app(EnsureVerifiedEmailContactAction::class);

    expect($action->execute(User::factory()->create(['email' => 'not-an-address'])))->toBeNull()
        ->and(UserContact::query()->count())->toBe(0);
});

it('backfills accounts that already exist', function () {
    $withEmail = User::factory()->count(3)->create();
    // One that already has a contact must not be duplicated.
    app(EnsureVerifiedEmailContactAction::class)->execute($withEmail->first());

    $this->artisan('identity:backfill-email-contacts')
        ->expectsOutputToContain('Created: 2')
        ->expectsOutputToContain('Already had a contact: 1')
        ->assertSuccessful();

    expect(UserContact::query()->where('type', 'email')->count())->toBe(3);
});

it('reports a contested address rather than resolving it', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    UserContact::query()->create([
        'user_id' => $owner->id,
        'type' => 'email',
        'value' => 'shared@example.com',
        'is_primary' => false,
        'verified_at' => now(),
    ]);
    User::factory()->create(['email' => 'shared@example.com']);

    // An address already held by another account needs a human, not a silent
    // winner — the backfill says so and leaves it.
    $this->artisan('identity:backfill-email-contacts')
        ->expectsOutputToContain('Skipped')
        ->assertSuccessful();

    expect(UserContact::query()->where('value', 'shared@example.com')->count())->toBe(1)
        ->and((int) UserContact::query()->where('value', 'shared@example.com')->sole()->user_id)
        ->toBe((int) $owner->id);
});

it('changes nothing on a dry run', function () {
    User::factory()->count(2)->create();

    $this->artisan('identity:backfill-email-contacts', ['--dry-run' => true])
        ->expectsOutputToContain('Would create: 2')
        ->assertSuccessful();

    expect(UserContact::query()->count())->toBe(0);
});

it('lets a backfilled account be found by the reset flow', function () {
    $user = User::factory()->create(['email' => 'teacher@example.com']);
    $this->artisan('identity:backfill-email-contacts')->assertSuccessful();

    // The actual point of the slice, asserted end to end.
    $this->withoutLocalizationMiddleware()
        ->post(route('password.otp.send'), ['contact' => 'teacher@example.com'])
        ->assertRedirect(route('password.otp.verify.form'));

    expect(session('password_reset_contact_id'))->not->toBeNull()
        ->and((int) UserContact::query()->findOrFail(session('password_reset_contact_id'))->user_id)
        ->toBe((int) $user->id);
});
