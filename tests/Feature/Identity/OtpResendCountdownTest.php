<?php

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Identity\Services\OtpService;
use App\Domains\Identity\Support\Wait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * BACKLOG C16 slice N3 (STATUS §5ny). The owner, waiting for a code on the
 * live site: "its not auto counting down" — and the refusal read "try again
 * in 1 minutes". The verify screens now receive the resend wait, count it
 * down and hold the Resend button until it ends; every wait is said the way
 * a person would.
 */
function countdownContact(): UserContact
{
    $user = User::factory()->create();

    return UserContact::query()->create(['user_id' => $user->id, 'type' => 'email', 'value' => 'countdown@example.test', 'verified_at' => now()]);
}

it('says a wait the way a person would', function () {
    expect(Wait::describe(1))->toBe('1 second')
        ->and(Wait::describe(45))->toBe('45 seconds')
        ->and(Wait::describe(60))->toBe('1 minute')
        ->and(Wait::describe(61))->toBe('2 minutes')
        ->and(Wait::describe(900))->toBe('15 minutes')
        ->and(Wait::describe(0))->toBe('1 second');
});

it('knows how long before another code may be sent, and says it in the refusal', function () {
    config()->set('otp.resend_cooldown_seconds', 60);
    config()->set('otp.max_sends', 3);
    $contact = countdownContact();
    $service = app(OtpService::class);
    RateLimiter::clear('otp:send:'.$contact->id.':login');
    RateLimiter::clear('otp:cooldown:'.$contact->id.':login');

    expect($service->retryAfterSeconds($contact, 'login'))->toBe(0);

    $service->send($contact, 'login');
    $wait = $service->retryAfterSeconds($contact, 'login');
    expect($wait)->toBeGreaterThan(50)->toBeLessThanOrEqual(60);

    try {
        $service->send($contact, 'login');
        $this->fail('the cooldown should have refused');
    } catch (ValidationException $e) {
        expect($e->errors()['contact'][0])->toMatch('/^Please wait (\d+ seconds|1 minute) before requesting a new code\.$/');
    }
});

it('hands the verify screen the wait and holds the Resend button, in both OTP flows', function () {
    config()->set('otp.resend_cooldown_seconds', 60);
    $contact = countdownContact();
    $service = app(OtpService::class);
    RateLimiter::clear('otp:send:'.$contact->id.':login');
    RateLimiter::clear('otp:cooldown:'.$contact->id.':login');

    // Before a code is sent: the button is live.
    $this->withoutLocalizationMiddleware()->withSession(['otp_login_contact_id' => $contact->id, 'otp_login_identifier' => $contact->value])
        ->get(route('otp.verify.form'))->assertOk()
        ->assertSee('data-retry-after="0"', false)
        ->assertDontSee('aria-disabled="true"', false);
    $live = $this->withoutLocalizationMiddleware()->withSession(['otp_login_contact_id' => $contact->id, 'otp_login_identifier' => $contact->value])
        ->get(route('otp.verify.form'))->getContent();
    preg_match('/id="otp-resend".*?>\s*(.*?)\s*<\/button>/s', $live, $button);
    expect(trim($button[1] ?? ''))->toBe('Resend code');

    // Right after one: the wait, the button disabled, the countdown's first reading.
    $service->send($contact, 'login');
    $response = $this->withoutLocalizationMiddleware()->withSession(['otp_login_contact_id' => $contact->id, 'otp_login_identifier' => $contact->value])
        ->get(route('otp.verify.form'))->assertOk()
        ->assertSee('aria-disabled="true"', false)
        ->assertSee('Resend code in 0', false);
    expect((int) preg_replace('/.*data-retry-after="(\d+)".*/s', '$1', $response->getContent()))->toBeGreaterThan(50);

    // The password-reset flow, the same way.
    RateLimiter::clear('otp:send:'.$contact->id.':password_reset');
    RateLimiter::clear('otp:cooldown:'.$contact->id.':password_reset');
    $this->withoutLocalizationMiddleware()->withSession(['password_reset_contact_value' => $contact->value, 'password_reset_contact_id' => $contact->id])
        ->get(route('password.otp.verify.form'))->assertOk()
        ->assertSee('data-retry-after="0"', false);
    $service->send($contact, 'password_reset');
    $this->withoutLocalizationMiddleware()->withSession(['password_reset_contact_value' => $contact->value, 'password_reset_contact_id' => $contact->id])
        ->get(route('password.otp.verify.form'))->assertOk()
        ->assertSee('aria-disabled="true"', false)
        ->assertSee('Resend code in 0', false);

    // In Dhivehi and Arabic the button has its own words.
    foreach (['dv', 'ar'] as $locale) {
        expect(trans('security.otp_resend_in', ['time' => '00:59'], $locale))->toContain('00:59')->not->toBe(trans('security.otp_resend_in', ['time' => '00:59'], 'en'));
    }
});
