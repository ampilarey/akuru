<?php

/**
 * Every route reachable without authentication is declared, with why.
 *
 * ## The case that argued for this
 *
 * `payments/bml/return` carried the middleware `['web']` and nothing else, and
 * a comment promising it *"ignores return URL state entirely"*. It did not
 * ignore it: a query parameter chose which transaction the server-side check
 * then verified, so replaying any completed transaction id confirmed a payment
 * nobody had paid for (#362).
 *
 * Nothing about that route looked unusual in a listing of 878 routes, because
 * **123 of them need no session and none of them had ever been justified**. A
 * route joining the public set looked exactly like a route that always
 * belonged there.
 *
 * ## What the reason has to say
 *
 * The four kinds are set out in the baseline, and one of them is the reason
 * this gate is worth having: **guarded in the handler**. The route is public
 * and the safety is an `abort_unless($request->user(), 403)` several files
 * away — correct, and completely invisible from the routing table. Eighteen
 * routes are like that. Writing it down is the only way a reader of the route
 * list can tell them from the genuinely open ones.
 *
 * `WriteRoutesAreGuardedTest` is the neighbouring gate and asks a different
 * question: it covers POST/PUT/DELETE regardless of auth. This one covers
 * anything reachable anonymously, GET included — which is what the payment
 * bug was.
 *
 * ## Scope
 *
 * A route counts as public when no middleware on it starts with `auth`. That
 * deliberately treats `auth:sanctum` as authenticated, and deliberately does
 * **not** try to interpret controller-level guards — inferring those is the
 * judgement this list exists to record rather than guess.
 */
it('declares every route reachable without authentication', function () {
    $declared = require __DIR__.'/Baselines/public_routes.php';

    $found = publicRoutes();

    $new = array_values(array_diff(array_keys($found), array_keys($declared)));
    sort($new);

    expect($new)->toBeEmpty(
        "These routes need no authentication and are not declared:\n  "
        .implode("\n  ", array_map(fn ($k) => $k.'  —  '.$found[$k], $new))
        ."\n\nAdd an `auth` middleware, or add the route to "
        ."tests/Architecture/Baselines/public_routes.php saying which kind it is:\n"
        ."  - public content — anonymous readers are the audience\n"
        ."  - verified by protocol — a signature stands in for a session (webhooks)\n"
        ."  - authentication itself — reachable before a session exists; throttle it\n"
        ."  - guarded in the handler — NAME the check, because the route list cannot show it\n"
        .'Read the #362 note in the baseline header first if this route touches money.'
    );

    $stale = array_values(array_diff(array_keys($declared), array_keys($found)));
    sort($stale);

    expect($stale)->toBeEmpty(
        "These declarations no longer match a public route — delete them:\n  "
        .implode("\n  ", $stale)
        ."\n\nThe list may only shrink. A route that has since gained `auth` is "
        .'good news: remove its entry.'
    );
});

/**
 * `METHOD uri` => the route name, for every route with no `auth` middleware.
 *
 * @return array<string, string>
 */
function publicRoutes(): array
{
    $routes = [];

    foreach (app('router')->getRoutes() as $route) {
        $middleware = app('router')->gatherRouteMiddleware($route);

        foreach ($middleware as $layer) {
            if (is_string($layer) && str_starts_with(class_basename($layer), 'Authenticate')) {
                continue 2;
            }
        }

        foreach ($route->gatherMiddleware() as $layer) {
            if (is_string($layer) && str_starts_with($layer, 'auth')) {
                continue 2;
            }
        }

        $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD') ?? 'GET';
        $routes[$method.' '.$route->uri()] = $route->getName() ?? '(unnamed)';
    }

    ksort($routes);

    return $routes;
}
