<?php

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Services\LogSmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * `CourseRegistrationController::setPassword` writes a password, a name, a
 * date of birth and a national ID onto `session('pending_user_id')`.
 *
 * `start` is a public POST that takes a phone number from the request body. On
 * the returning-user branch it resolves that number to an existing account and
 * writes `pending_user_id` **at the moment the code is sent** — before anyone
 * has entered anything. `setPassword` checked only that the value was present.
 *
 * So a visitor who knew somebody's mobile number could start the funnel as
 * them, never touch `verify`, and claim the account outright. The code went to
 * the owner's phone and was never needed.
 *
 * ## Scope, established by running it rather than by reading
 *
 * - An account whose contact is **already verified** is safe: `start`
 *   short-circuits it to the checkout login screen and never writes the
 *   session keys. That is pinned below, because it is load-bearing.
 * - An account whose contact is **not yet verified** was fully claimable.
 *   That is every account created by `AccountResolverService`
 *   (`verified_at => null`) before its owner first signs in — which at go-live
 *   is every bulk-imported parent and student.
 *
 * ## Two earlier versions of this file proved nothing
 *
 * The first stored the victim's contact as `9995678`. Contacts are normalised
 * to `+9609995678`, so the lookup missed, the funnel created a **brand-new
 * user**, and the attack "failed" against an account that did not exist. The
 * second could not send an OTP at all, because `LogSmsSender` had no
 * `sendOtp` — the defect fixed in the previous slice.
 *
 * Both reported the system as safe. Both were wrong. The happy-path case below
 * is what finally exposed that: until a legitimate run works, a refusal means
 * nothing.
 */
function funnelSms(): LogSmsSender
{
    app()->instance(SmsSenderInterface::class, $log = new LogSmsSender);

    return $log;
}

function funnelAccount(bool $contactVerified): User
{
    $user = User::factory()->create([
        'name' => 'Aishath Victim',
        'password' => Hash::make('victims-own-password'),
        'force_password_change' => true,
    ]);

    UserContact::query()->create([
        'user_id' => $user->id,
        'type' => 'mobile',
        // Normalised, as the app stores it. The raw form silently matches
        // nothing and quietly makes this whole file vacuous.
        'value' => '+9609995678',
        'is_primary' => true,
        'verified_at' => $contactVerified ? now() : null,
    ]);

    return $user;
}

function startFunnelAs(string $mobile = '9995678'): void
{
    test()->withoutLocalizationMiddleware()
        ->post('/courses/register/start', ['contact_type' => 'mobile', 'contact_value' => $mobile]);
}

function postSetPassword(array $overrides = []): Illuminate\Testing\TestResponse
{
    return test()->withoutLocalizationMiddleware()
        ->post('/courses/register/set-password', array_merge([
            'first_name' => 'Attacker',
            'last_name' => 'Person',
            'gender' => 'male',
            'dob' => '1990-01-01',
            'id_type' => 'national_id',
            'national_id' => 'A999999',
            'password' => 'taken-over-123',
            'password_confirmation' => 'taken-over-123',
        ], $overrides));
}

it('refuses to claim an account when the code was never entered', function () {
    funnelSms();
    $victim = funnelAccount(contactVerified: false);

    // Step 1 as any anonymous visitor, with somebody else's number. The code
    // goes to them; the attacker never sees it.
    startFunnelAs();

    expect((int) session('pending_user_id'))->toBe((int) $victim->id);

    // Step 3, skipping step 2.
    postSetPassword();

    $fresh = $victim->fresh();

    expect(Hash::check('taken-over-123', $fresh->password))->toBeFalse()
        ->and(Hash::check('victims-own-password', $fresh->password))->toBeTrue()
        // The same request rewrites identity, so a claim also overwrote who
        // the account said it was.
        ->and($fresh->name)->toBe('Aishath Victim')
        ->and($fresh->national_id)->not->toBe('A999999')
        ->and($fresh->force_password_change)->toBeTrue();
});

it('lets the real owner set a password after entering the code', function () {
    $log = funnelSms();
    $owner = funnelAccount(contactVerified: false);

    startFunnelAs();

    // The code as the owner receives it. On an environment with live SMS off
    // this is the log, which is how anybody signs in on staging.
    preg_match('/\b(\d{6})\b/', $log->sent[0]['body'] ?? '', $found);
    expect($found[1] ?? null)->not->toBeNull();

    test()->withoutLocalizationMiddleware()
        ->post('/courses/register/verify', ['code' => $found[1]])
        ->assertRedirect(route('courses.register.set-password'));

    postSetPassword([
        'first_name' => 'Aishath',
        'last_name' => 'Real',
        'national_id' => 'A123456',
        'password' => 'my-own-choice-99',
        'password_confirmation' => 'my-own-choice-99',
    ]);

    // Without this case the fix could be "refuse everything", which passes the
    // test above and breaks the funnel for every real user.
    expect(Hash::check('my-own-choice-99', $owner->fresh()->password))->toBeTrue()
        ->and($owner->fresh()->force_password_change)->toBeFalse();
});

it('does not let one verification authorise a second password write', function () {
    $log = funnelSms();
    $owner = funnelAccount(contactVerified: false);

    startFunnelAs();
    preg_match('/\b(\d{6})\b/', $log->sent[0]['body'] ?? '', $found);

    test()->withoutLocalizationMiddleware()
        ->post('/courses/register/verify', ['code' => $found[1]]);

    postSetPassword(['password' => 'first-choice-99', 'password_confirmation' => 'first-choice-99']);
    postSetPassword(['password' => 'second-choice-99', 'password_confirmation' => 'second-choice-99']);

    expect(Hash::check('first-choice-99', $owner->fresh()->password))->toBeTrue();
});

it('never reaches the funnel at all for an account whose contact is verified', function () {
    funnelSms();
    funnelAccount(contactVerified: true);

    startFunnelAs();

    // `start` short-circuits a verified contact to the checkout login screen
    // without writing the session keys. This is the reason the defect above
    // was not worse than it was, so it is pinned rather than assumed.
    expect(session('pending_user_id'))->toBeNull();
});

it('does not show the set-password screen before the code is entered', function () {
    funnelSms();
    funnelAccount(contactVerified: false);

    startFunnelAs();

    test()->withoutLocalizationMiddleware()
        ->get('/courses/register/set-password')
        ->assertRedirect(route('courses.register.otp'));
});

it('does not sign anybody in as the account they merely named', function () {
    // The second door, found by auditing the rest of the same controller.
    // `enroll` called `Auth::login()` straight off `pending_user_id` and only
    // then checked `hasVerifiedContact()` — and a redirect does not undo a
    // login. Two POSTs and somebody else's mobile number produced a session as
    // them, while the screen said "Please verify your contact first".
    funnelSms();
    $victim = funnelAccount(contactVerified: false);

    startFunnelAs();

    test()->withoutLocalizationMiddleware()
        ->post('/courses/register/enroll', ['course_ids' => [1]]);

    expect(auth()->id())->toBeNull();
});

it('does not sign anybody in through the continue screen either', function () {
    // Same shape, same controller, one method along. Fixed together because
    // fixing one and not the other is how a door stays open.
    funnelSms();
    funnelAccount(contactVerified: false);

    startFunnelAs();

    test()->withoutLocalizationMiddleware()
        ->get('/courses/register/continue');

    expect(auth()->id())->toBeNull();
});

it('signs the real owner in once they have entered the code', function () {
    $log = funnelSms();
    $owner = funnelAccount(contactVerified: false);

    startFunnelAs();
    preg_match('/\b(\d{6})\b/', $log->sent[0]['body'] ?? '', $found);

    test()->withoutLocalizationMiddleware()
        ->post('/courses/register/verify', ['code' => $found[1]]);

    postSetPassword([
        'first_name' => 'Aishath', 'last_name' => 'Real', 'national_id' => 'A123456',
        'password' => 'my-own-choice-99', 'password_confirmation' => 'my-own-choice-99',
    ]);

    // The other half of the pair: the funnel must still end with the owner
    // signed in, or the fix has simply broken registration.
    expect(auth()->id())->toBe($owner->id);
});
