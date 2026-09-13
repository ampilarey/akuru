<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * SPEC §32 "Authentication and Rate Limiting".
 *
 * > Throttle: Login attempts · Registration attempts · Password reset requests ·
 * > OTP send requests · OTP verification attempts
 * >
 * > Rate limits must apply: **Per IP address** · Per account identifier where
 * > applicable · Per phone number for OTP
 *
 * **Three of the five were already enforced, and enforced well.**
 * `LoginRequest::ensureIsNotRateLimited()` caps login at five attempts keyed by
 * identifier **and** IP. `OtpService` caps sends and verification attempts per
 * contact using §32's own numbers, which an earlier slice corrected in
 * `config/otp.php` (3 per 15 minutes, 60-second cooldown) after finding the
 * hardcoded values were nearly double the spec's.
 *
 * Two were missing, and they share a shape.
 *
 * **Registration had no limit of any kind** — no middleware on the route, no
 * `RateLimiter` in `RegisteredUserController`. `unique:users,email` answers
 * "does this address already have an account?" on every attempt, so an
 * unthrottled endpoint is both an open account-creation firehose and a free
 * enumeration oracle.
 *
 * **Password reset and OTP request were limited per contact, which cannot see
 * the attack that matters.** `PasswordOtpController::sendOtp` looks a user up
 * by national ID, passport, email or phone **before** `OtpService` is reached —
 * so every probe uses a different contact and never touches the per-contact
 * counter. That leaves an unlimited "does this national ID exist?" oracle, and
 * §32's per-IP limit is exactly what closes it.
 */
uses(RefreshDatabase::class);

it('registers a named limiter for each §32 gap', function () {
    // Named rather than `throttle:10,60` in the route, because §32 requires the
    // numbers be "configurable in system settings" — a literal in a route file
    // is a deploy.
    foreach (['auth-register', 'auth-password-reset', 'auth-otp-request'] as $limiter) {
        expect(RateLimiter::limiter($limiter))->not->toBeNull("limiter [{$limiter}] is not registered");
    }
});

it('throttles registration, which had no limit at all', function () {
    config(['auth-throttle.register_per_ip' => 2]);

    for ($i = 1; $i <= 2; $i++) {
        $this->withoutLocalizationMiddleware()
            ->post('/register', [
                'name' => 'Person '.$i,
                'email' => 'person'.$i.'@example.com',
                'password' => 'password-'.$i,
                'password_confirmation' => 'password-'.$i,
            ])
            ->assertStatus(302);
        auth()->logout();
    }

    $this->withoutLocalizationMiddleware()
        ->post('/register', [
            'name' => 'Person 3',
            'email' => 'person3@example.com',
            'password' => 'password-3',
            'password_confirmation' => 'password-3',
        ])
        ->assertStatus(429);
});

it('throttles password reset requests per IP, which the per-contact limit cannot', function () {
    // The point of the per-IP limit: each of these probes a *different*
    // identifier, so `OtpService`'s per-contact counter is never touched once.
    config(['auth-throttle.password_reset_per_ip' => 3]);

    for ($i = 1; $i <= 3; $i++) {
        $this->withoutLocalizationMiddleware()
            ->post('/forgot-password', ['identifier' => 'A00000'.$i])
            ->assertStatus(302);
    }

    $this->withoutLocalizationMiddleware()
        ->post('/forgot-password', ['identifier' => 'A000004'])
        ->assertStatus(429);
});

it('throttles OTP requests per IP as well as per phone', function () {
    config(['auth-throttle.otp_request_per_ip' => 2]);

    for ($i = 1; $i <= 2; $i++) {
        $this->withoutLocalizationMiddleware()
            ->post('/otp/request', ['contact' => '79000'.$i.'0'])
            ->assertStatus(302);
    }

    $this->withoutLocalizationMiddleware()
        ->post('/otp/request', ['contact' => '7900030'])
        ->assertStatus(429);
});

it('puts a throttle on every §32 endpoint that lacked one', function () {
    $expected = [
        'password.email' => 'throttle:auth-password-reset',
        'password.store' => 'throttle:auth-password-reset',
        'password.otp.send' => 'throttle:auth-password-reset',
        'password.otp.resend' => 'throttle:auth-password-reset',
        'otp.request' => 'throttle:auth-otp-request',
        'otp.resend' => 'throttle:auth-otp-request',
    ];

    foreach ($expected as $name => $middleware) {
        $route = Route::getRoutes()->getByName($name);
        expect($route)->not->toBeNull("route [{$name}] is missing");
        // `toContain` takes further expected values, not a message, so the
        // route name goes in a separate assertion rather than as an argument.
        expect(in_array($middleware, $route->gatherMiddleware(), true))
            ->toBeTrue("route [{$name}] is not throttled");
    }

    // `register` has no route name, so it is found by URI.
    $register = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === 'register' && in_array('POST', $route->methods(), true));

    expect($register)->not->toBeNull()
        ->and(in_array('throttle:auth-register', $register->gatherMiddleware(), true))->toBeTrue();
});

it('leaves login to its own limiter rather than stacking a second', function () {
    // `LoginRequest::ensureIsNotRateLimited()` already throttles by identifier
    // and IP, and reports how long is left. A route-level limiter over the top
    // would make the lockout message disagree with the lockout.
    $login = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($route) => $route->uri() === 'login' && in_array('POST', $route->methods(), true));

    expect($login)->not->toBeNull();

    foreach ($login->gatherMiddleware() as $middleware) {
        expect(is_string($middleware) && str_starts_with($middleware, 'throttle:'))->toBeFalse();
    }

    $source = (string) file_get_contents(base_path('app/Http/Requests/Auth/LoginRequest.php'));
    expect($source)->toContain('ensureIsNotRateLimited')
        // §32: "Per IP address · Per account identifier where applicable."
        ->and($source)->toContain('$this->ip()');
});
