<?php

use Illuminate\Support\Facades\Route;

/**
 * Every numeric route throttle carries its own prefix (STATUS §5ik).
 *
 * Laravel's `throttle:N,1` keys on the signed-in user alone — or the IP for a
 * guest (`ThrottleRequests::resolveRequestSignature`) — not on the route. So
 * every route with a plain numeric throttle shared **one counter per person**,
 * each checking it against its own limit: ten cart adds in a minute got the
 * checkout a 429 (found by the B3 walk, 2026-09-26, fixed for the bookstore
 * that day). About thirty other routes — library checkout, wallet redeem,
 * gift cards, the course registration flow, the public forms — still shared
 * that counter until this sweep. The third argument, a prefix, gives each
 * route (or each deliberate group, like the two cart routes) a counter of its
 * own. This test pins that on the registered routes, so the next
 * `->middleware('throttle:10,1')` fails CI rather than a walk.
 */
it('gives every numeric route throttle its own prefix', function () {
    $bare = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'throttle:')) {
                continue;
            }
            $parts = explode(',', substr($middleware, strlen('throttle:')));
            // A named limiter (`throttle:auth-register`) keys itself.
            if (! ctype_digit($parts[0])) {
                continue;
            }
            if (count($parts) < 3 || $parts[2] === '') {
                $bare[] = implode('|', $route->methods()).' '.$route->uri().' → '.$middleware;
            }
        }
    }

    expect($bare)->toBe([], "These routes share one throttle counter per person; give each `throttle:N,M` a prefix as its third argument:\n".implode("\n", $bare));
});

it('no longer refuses a second route because a first one was used up', function () {
    // Both took `throttle:10,1` before: a registrant who started ten times in a
    // minute (say, ten wrong forms) was then refused the OTP check itself.
    for ($i = 0; $i < 10; $i++) {
        $this->withoutLocalizationMiddleware()->post(route('courses.register.start'), []);
    }
    $this->withoutLocalizationMiddleware()->post(route('courses.register.start'), [])->assertStatus(429);

    $response = $this->withoutLocalizationMiddleware()->post(route('courses.register.verify'), []);
    expect($response->status())->not->toBe(429);
});
