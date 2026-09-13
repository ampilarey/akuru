<?php

namespace App\Domains\Identity\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerAuthThrottles();
    }

    /**
     * SPEC §32's per-IP limits, as named limiters.
     *
     * Named rather than `throttle:10,60` written into the route, because §32
     * requires the numbers be "configurable in system settings" — a literal in
     * a route file is a deploy, and the whole point of these is that an
     * operator can tighten them while an attack is happening.
     *
     * Login is deliberately absent: `LoginRequest::ensureIsNotRateLimited()`
     * already throttles it per identifier **and** IP, and a second limiter over
     * the top would make the lockout message disagree with the lockout.
     */
    private function registerAuthThrottles(): void
    {
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinutes(
            (int) config('auth-throttle.register_window_minutes', 60),
            (int) config('auth-throttle.register_per_ip', 10),
        )->by($request->ip()));

        RateLimiter::for('auth-password-reset', fn (Request $request) => Limit::perMinutes(
            (int) config('auth-throttle.password_reset_window_minutes', 15),
            (int) config('auth-throttle.password_reset_per_ip', 10),
        )->by($request->ip()));

        RateLimiter::for('auth-otp-request', fn (Request $request) => Limit::perMinutes(
            (int) config('auth-throttle.otp_request_window_minutes', 15),
            (int) config('auth-throttle.otp_request_per_ip', 15),
        )->by($request->ip()));
    }
}
