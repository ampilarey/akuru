<?php

use App\Domains\Identity\Models\OtpAbuseEvent;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Identity\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §32 "OTP-Ready Rules", which states four rules and then the reason they
 * exist: "This protects future Dhiraagu SMS integration from cost abuse and
 * spam." Every send is a message somebody pays for.
 *
 * What the code did before:
 *
 *   | §32                                   | was            |
 *   |---------------------------------------|----------------|
 *   | max 3 sends per number per 15 minutes | 5 per 60 min   |
 *   | minimum 60-second resend cooldown     | **30 seconds** |
 *   | abuse event logging for admin review  | **none**       |
 *   | configurable in system settings       | hardcoded      |
 *
 * The cooldown is the one that mattered most: half the mandated floor doubles
 * the achievable send rate, and therefore the bill.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('otp_send:1:verify_contact');
    config()->set('otp.max_sends', 3);
    config()->set('otp.send_window_minutes', 15);
    config()->set('otp.resend_cooldown_seconds', 60);
});

function otpContact(): UserContact
{
    $user = User::factory()->create();

    return UserContact::query()->create([
        'user_id' => $user->id,
        'type' => 'email',
        'value' => 'otp-limits@example.test',
    ]);
}

it('ships SPEC §32 numbers as the defaults', function () {
    // Read from a fresh config rather than the values the beforeEach sets, so
    // this asserts what an untouched install actually does.
    $shipped = require base_path('config/otp.php');

    expect($shipped['max_sends'])->toBe(3)
        ->and($shipped['send_window_minutes'])->toBe(15)
        // The specific regression: 30 was half the mandated minimum.
        ->and($shipped['resend_cooldown_seconds'])->toBe(60);
});

it('makes every limit configurable, as §32 requires', function () {
    config()->set('otp.max_sends', 9);
    config()->set('otp.resend_cooldown_seconds', 5);

    $service = app(OtpService::class);
    $reflect = new ReflectionClass($service);

    $max = $reflect->getMethod('maxSends');
    $max->setAccessible(true);
    $cooldown = $reflect->getMethod('resendCooldownSeconds');
    $cooldown->setAccessible(true);

    // An operator under attack must be able to tighten these without a deploy.
    expect($max->invoke($service))->toBe(9)
        ->and($cooldown->invoke($service))->toBe(5);
});

it('refuses a resend inside the cooldown and logs it', function () {
    $contact = otpContact();
    $service = app(OtpService::class);

    $service->send($contact, 'verify_contact');

    expect(fn () => $service->send($contact, 'verify_contact'))
        ->toThrow(ValidationException::class);

    // §32: "OTP abuse event logging for admin review." Before this, a tripped
    // limit threw at the person and left no trace, so an admin could not tell
    // one confused parent from somebody burning the SMS credit.
    $event = OtpAbuseEvent::query()->where('kind', 'resend_cooldown')->first();
    expect($event)->not->toBeNull()
        ->and($event->channel)->toBe('email');
});

it('stops at the configured send ceiling and logs that too', function () {
    $contact = otpContact();
    $service = app(OtpService::class);

    // Travelled rather than disabling the cooldown: `resendCooldownSeconds()`
    // floors at 1 on purpose, so an operator cannot switch the protection off
    // entirely by setting it to zero. Three legitimate, spaced-out sends.
    for ($i = 0; $i < 3; $i++) {
        $service->send($contact, 'verify_contact');
        $this->travel(61)->seconds();
    }

    expect(fn () => $service->send($contact, 'verify_contact'))
        ->toThrow(ValidationException::class);

    $event = OtpAbuseEvent::query()->where('kind', 'send_rate')->first();
    expect($event)->not->toBeNull()
        ->and($event->threshold)->toBe(3);
});

it('never stores a raw phone number or address in the abuse log', function () {
    $contact = otpContact();
    $service = app(OtpService::class);
    $service->send($contact, 'verify_contact');
    try {
        $service->send($contact, 'verify_contact');
    } catch (ValidationException) {
        // expected
    }

    $event = OtpAbuseEvent::query()->firstOrFail();

    // These rows are the ones most likely to be exported and mailed around
    // while somebody investigates, so the contact is hashed — with the last
    // four characters kept so a human can recognise their own number without
    // the list being a phone book.
    expect($event->contact_hash)->toHaveLength(64)
        ->and($event->contact_hash)->not->toContain('otp-limits@example.test')
        ->and($event->contact_tail)->toBe('test')
        ->and(json_encode($event->toArray()))->not->toContain('otp-limits@example.test');
});

it('lets a legitimate resend through once the cooldown has passed', function () {
    $contact = otpContact();
    $service = app(OtpService::class);

    $service->send($contact, 'verify_contact');
    $this->travel(61)->seconds();
    $service->send($contact, 'verify_contact');

    // The limit must not become a wall for the ordinary case: a parent who
    // waited is entitled to a second code.
    expect(OtpAbuseEvent::query()->count())->toBe(0);
});

it('logs a cooldown trip on the unauthenticated registration path too', function () {
    $service = app(OtpService::class);
    \Illuminate\Support\Facades\RateLimiter::clear('new_reg_otp_send:'.md5('email'.'newcomer@example.test'));
    \Illuminate\Support\Facades\RateLimiter::clear('new_reg_otp_cooldown:'.md5('email'.'newcomer@example.test'));

    $service->sendForNewRegistration('email', 'newcomer@example.test');

    expect(fn () => $service->sendForNewRegistration('email', 'newcomer@example.test'))
        ->toThrow(ValidationException::class);

    // This branch tripped silently while the signed-in one logged. It is the
    // more exposed of the two: no account is needed to reach it, so it is the
    // cheapest place in the app to burn SMS credit — exactly the cost abuse
    // §32 names.
    $event = OtpAbuseEvent::query()->where('kind', 'resend_cooldown')->first();
    expect($event)->not->toBeNull()
        ->and($event->user_id)->toBeNull()
        ->and($event->contact_tail)->toBe('test');
});

it('groups the abuse log by contact for review, without exposing the contact', function () {
    $contact = otpContact();
    $action = app(\App\Domains\Identity\Actions\RecordOtpAbuseEventAction::class);

    // One contact tripping three times, and a second tripping once. §32's log
    // is "for admin review", and the review question is one person or many.
    $action->execute('resend_cooldown', $contact, 'verify_contact', 1, 1);
    $action->execute('resend_cooldown', $contact, 'verify_contact', 1, 1);
    $action->execute('send_rate', $contact, 'verify_contact', 4, 3);
    $action->forValue('send_rate', '+9607654321', 'mobile', 'verify_contact', 4, 3);

    $payload = app(\App\Domains\Identity\Actions\ListOtpAbuseEventsAction::class)->execute(7);

    expect($payload['groups'])->toHaveCount(2)
        ->and($payload['total_trips'])->toBe(4)
        // Busiest contact first: that is the one worth looking at.
        ->and($payload['groups'][0]['trips'])->toBe(3)
        ->and($payload['groups'][0]['kinds'])->toBe(['resend_cooldown' => 2, 'send_rate' => 1])
        // The thresholds are configurable, so the screen states the ones in
        // force rather than leaving an admin to guess.
        ->and($payload['limits']['resend_cooldown_seconds'])->toBe(60);

    // Neither the screen payload nor the CSV may carry a contact back out.
    $serialised = json_encode($payload).json_encode(app(\App\Domains\Identity\Actions\ListOtpAbuseEventsAction::class)->rows(7));
    expect($serialised)->not->toContain('otp-limits@example.test')
        ->and($serialised)->not->toContain('+9607654321')
        ->and($serialised)->toContain('4321');
});

it('keeps the abuse review screen to super admins', function () {
    $plain = User::factory()->create();
    $super = User::factory()->create();
    $super->assignRole('super_admin');

    // This list names the contacts that have been refused. It is a security
    // log, so it sits behind the same door as the rest of admin/users.
    $this->withoutLocalizationMiddleware()
        ->actingAs($plain)->get(route('admin.users.otp-abuse'))->assertForbidden();

    $this->withoutLocalizationMiddleware()
        ->actingAs($super)->get(route('admin.users.otp-abuse'))->assertOk();
});

it('prunes abuse events past retention but keeps recent ones', function () {
    $contact = otpContact();

    app(\App\Domains\Identity\Actions\RecordOtpAbuseEventAction::class)
        ->execute('send_rate', $contact, 'verify_contact', 4, 3);
    OtpAbuseEvent::query()->update(['occurred_at' => now()->subDays(200)]);

    app(\App\Domains\Identity\Actions\RecordOtpAbuseEventAction::class)
        ->execute('send_rate', $contact, 'verify_contact', 4, 3);

    $this->artisan('akuru:prune-expired')->assertExitCode(0);

    // The point of the log is a pattern across days, not forever.
    expect(OtpAbuseEvent::query()->count())->toBe(1);
});
