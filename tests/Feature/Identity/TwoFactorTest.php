<?php

use App\Domains\Identity\Actions\TwoFactorAction;
use App\Domains\Identity\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Identity\Services\OtpService;
use App\Domains\Identity\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * STATUS §5lk, two-step sign-in: a person turns it on with an authenticator
 * app and gets eight recovery codes once; from then on the password (or the
 * OTP) only gets them to the challenge, and a code from the app — never one
 * already used — or a recovery code, which is spent, signs them in.
 */
function twoFactorUser(): User
{
    $user = User::factory()->create(['email' => 'two@example.com']);
    UserContact::create(['user_id' => $user->id, 'type' => 'email', 'value' => 'two@example.com', 'is_primary' => true, 'verified_at' => now()]);

    return $user;
}

/** @return list<string> the recovery codes */
function turnOnTwoFactor(User $user): array
{
    $action = app(TwoFactorAction::class);
    $action->start($user);
    $secret = (string) $user->refresh()->two_factor_secret;

    // Confirmed with last step's code, so the current one is still unused at sign-in.
    return $action->confirm($user, Totp::code($secret, Totp::step() - 1));
}

function twoFactorNow(User $user, int $offset = 0): string
{
    return Totp::code((string) $user->refresh()->two_factor_secret, Totp::step() + $offset);
}

function twoFactorPage(?User $user = null)
{
    $t = test()->withoutLocalizationMiddleware();

    return $user ? $t->actingAs($user) : $t;
}

it('turns on with a first code from the app, shows eight recovery codes once, and keeps the secret encrypted', function () {
    $user = twoFactorUser();

    twoFactorPage($user)->get(route('account.two-factor'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Identity/TwoFactor')->where('status.enabled', false)->where('pending', null));

    twoFactorPage($user)->post(route('account.two-factor.start'))->assertRedirect();
    twoFactorPage($user)->get(route('account.two-factor'))
        ->assertInertia(fn ($page) => $page->where('status.pending', true)->has('pending.secret')->where('pending.uri', fn ($uri) => str_starts_with($uri, 'otpauth://totp/')));

    twoFactorPage($user)->post(route('account.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
    expect($user->refresh()->hasTwoFactor())->toBeFalse();

    twoFactorPage($user)->post(route('account.two-factor.confirm'), ['code' => twoFactorNow($user)])
        ->assertSessionHas('two_factor_codes', fn ($codes) => count($codes) === 8);
    expect($user->refresh()->hasTwoFactor())->toBeTrue()
        ->and(DB::table('users')->where('id', $user->id)->value('two_factor_secret'))->not->toBe($user->two_factor_secret)
        ->and($user->toArray())->not->toHaveKey('two_factor_secret')->not->toHaveKey('two_factor_recovery_codes');

    twoFactorPage($user)->get(route('account.two-factor'))
        ->assertInertia(fn ($page) => $page->where('status.enabled', true)->where('status.recovery_left', 8)->has('recovery_codes', 8)->where('pending', null));
    twoFactorPage($user)->get(route('account.two-factor'))->assertInertia(fn ($page) => $page->where('recovery_codes', null));
});

it('stops a right password at the challenge, and signs in only with a fresh code from the app', function () {
    $user = twoFactorUser();
    turnOnTwoFactor($user);

    twoFactorPage()->post(route('login'), ['identifier' => 'two@example.com', 'password' => 'password', 'remember' => '1'])
        ->assertRedirect(route('two-factor.challenge'));
    $this->assertGuest();
    twoFactorPage()->get(route('two-factor.challenge'))->assertOk()->assertSee('data-testid="two-factor-form"', false);

    twoFactorPage()->post(route('two-factor.challenge.store'), ['code' => '123456'])->assertSessionHasErrors('code');
    $this->assertGuest();

    $code = twoFactorNow($user);
    twoFactorPage()->post(route('two-factor.challenge.store'), ['code' => $code])->assertRedirect();
    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->last_login_at)->not->toBeNull();

    // The same code again, on a new sign-in, is refused; the next one is not.
    auth()->logout();
    twoFactorPage()->post(route('login'), ['identifier' => 'two@example.com', 'password' => 'password']);
    twoFactorPage()->post(route('two-factor.challenge.store'), ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest();
    twoFactorPage()->post(route('two-factor.challenge.store'), ['code' => twoFactorNow($user, 1)])->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

it('takes a recovery code once, and never a wrong password', function () {
    $user = twoFactorUser();
    $codes = turnOnTwoFactor($user);

    twoFactorPage()->post(route('login'), ['identifier' => 'two@example.com', 'password' => 'wrong'])->assertSessionHasErrors('identifier');
    twoFactorPage()->get(route('two-factor.challenge'))->assertRedirect(route('login'));

    twoFactorPage()->post(route('login'), ['identifier' => 'two@example.com', 'password' => 'password']);
    twoFactorPage()->post(route('two-factor.challenge.store'), ['code' => strtoupper($codes[0])])->assertRedirect();
    $this->assertAuthenticatedAs($user);
    expect(count($user->refresh()->two_factor_recovery_codes))->toBe(7);

    auth()->logout();
    twoFactorPage()->post(route('login'), ['identifier' => 'two@example.com', 'password' => 'password']);
    twoFactorPage()->post(route('two-factor.challenge.store'), ['code' => $codes[0]])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('waits after five wrong codes, and forgets a challenge left for ten minutes', function () {
    $user = twoFactorUser();
    turnOnTwoFactor($user);

    twoFactorPage()->post(route('login'), ['identifier' => 'two@example.com', 'password' => 'password']);
    foreach (range(1, 5) as $n) {
        twoFactorPage()->post(route('two-factor.challenge.store'), ['code' => '00000'.$n])->assertSessionHasErrors('code');
    }
    twoFactorPage()->post(route('two-factor.challenge.store'), ['code' => twoFactorNow($user)])
        ->assertSessionHasErrors(['code' => __('security.error_throttled', ['seconds' => 60])]);
    $this->assertGuest();

    $this->travel(11)->minutes();
    twoFactorPage()->post(route('two-factor.challenge.store'), ['code' => twoFactorNow($user)])->assertRedirect(route('login'));
    twoFactorPage()->get(route('two-factor.challenge'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('sends an OTP sign-in to the challenge too, and leaves people without it untouched', function () {
    $user = twoFactorUser();
    turnOnTwoFactor($user);
    $this->mock(OtpService::class, fn ($mock) => $mock->shouldReceive('verify')->once());

    $contact = UserContact::query()->where('user_id', $user->id)->sole();
    twoFactorPage()->withSession(['otp_login_contact_id' => $contact->id])->post(route('otp.verify'), ['code' => '123456'])
        ->assertRedirect(route('two-factor.challenge'));
    $this->assertGuest();
    expect(session(TwoFactorChallengeController::SESSION)['id'])->toBe($user->id)
        ->and(session('otp_login_contact_id'))->toBeNull();

    $plain = User::factory()->create(['email' => 'plain@example.com']);
    UserContact::create(['user_id' => $plain->id, 'type' => 'email', 'value' => 'plain@example.com', 'is_primary' => true, 'verified_at' => now()]);
    twoFactorPage()->post(route('login'), ['identifier' => 'plain@example.com', 'password' => 'password'])->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($plain);
});

it('needs the password to turn it off or to get new recovery codes', function () {
    $user = twoFactorUser();
    $old = turnOnTwoFactor($user);

    twoFactorPage($user)->post(route('account.two-factor.codes'), ['password' => 'wrong'])->assertSessionHasErrors('password');
    twoFactorPage($user)->post(route('account.two-factor.codes'), ['password' => 'password'])
        ->assertSessionHas('two_factor_codes', fn ($codes) => count($codes) === 8 && array_intersect($codes, $old) === []);

    twoFactorPage($user)->post(route('account.two-factor.disable'), ['password' => 'wrong'])->assertSessionHasErrors('password');
    expect($user->refresh()->hasTwoFactor())->toBeTrue();
    twoFactorPage($user)->post(route('account.two-factor.disable'), ['password' => 'password'])->assertSessionHas('success', __('security.off_flash'));
    expect($user->refresh()->hasTwoFactor())->toBeFalse()->and($user->two_factor_secret)->toBeNull();

    // Only ever the signed-in person's own; a guest is sent to sign in.
    auth()->logout();
    twoFactorPage()->post(route('account.two-factor.start'))->assertRedirect();
    expect($user->refresh()->two_factor_secret)->toBeNull();
});
