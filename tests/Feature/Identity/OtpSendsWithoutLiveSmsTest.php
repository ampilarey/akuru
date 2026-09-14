<?php

use App\Domains\Identity\Models\Otp;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Identity\Services\OtpService;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Services\LogSmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * OTP login has to work when live SMS is off, because that is every
 * environment except one.
 *
 * `SmsSenderInterface` binds to `LogSmsSender` unless `APP_ENV=production` and
 * `SMS_LIVE` are both set — so local, staging and any production without the
 * flag all use it. `OtpService::dispatchCode` calls `sendOtp()`, which the
 * interface did not declare and `LogSmsSender` therefore did not implement.
 *
 * Every mobile OTP threw `Call to undefined method`, `OtpService::send` caught
 * it, deleted the code it had just written, and told the user *"Unable to send
 * verification code. Please try again."*
 *
 * Found while trying to drive the public registration funnel in a test: the
 * funnel would not proceed, and the reason turned out not to be the funnel.
 */
it('sends a mobile OTP through the log driver', function () {
    app()->instance(SmsSenderInterface::class, $log = new LogSmsSender);

    $user = User::factory()->create();
    $contact = UserContact::query()->create([
        'user_id' => $user->id,
        'type' => 'mobile',
        'value' => '7712345',
        'is_primary' => true,
        'verified_at' => null,
    ]);

    app(OtpService::class)->send($contact, 'login');

    // The code was written and kept. `send()` deletes the row when dispatch
    // throws, so a surviving row is itself the evidence dispatch worked.
    expect(Otp::query()->where('user_contact_id', $contact->id)->count())->toBe(1)
        ->and($log->sent)->toHaveCount(1)
        ->and($log->sent[0]['options']['type'])->toBe('otp')
        // Readable in the log is the point: that is how somebody signs in on
        // staging, where no handset receives anything.
        ->and($log->sent[0]['body'])->toContain('verification code');
});

it('logs a code that actually verifies', function () {
    app()->instance(SmsSenderInterface::class, $log = new LogSmsSender);

    $user = User::factory()->create();
    $contact = UserContact::query()->create([
        'user_id' => $user->id,
        'type' => 'mobile',
        'value' => '7798765',
        'is_primary' => true,
        'verified_at' => null,
    ]);

    app(OtpService::class)->send($contact, 'login');

    preg_match('/\b(\d{6})\b/', $log->sent[0]['body'], $found);

    expect($found[1] ?? null)->not->toBeNull();

    // The whole round trip, which is what "OTP login works" means. A logged
    // message containing some number would not prove it.
    app(OtpService::class)->verify($contact, 'login', $found[1]);

    expect(Otp::query()->where('user_contact_id', $contact->id)->whereNotNull('used_at')->count())->toBe(1);
});
