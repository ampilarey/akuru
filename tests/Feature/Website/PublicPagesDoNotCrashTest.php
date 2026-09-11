<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/**
 * Every public page must render without crashing.
 *
 * `PublicRouteNamesTest` asserts these route *names are registered*. It never
 * issues a request — so `public.about` passed for as long as the page existed
 * while the page itself returned **500 to every visitor**:
 *
 *     SQLSTATE[42S22]: Unknown column 'is_active' in
 *     select * from `testimonials` where `is_active` = 1 order by `sort_order`
 *
 * `Testimonial` has `is_public` and `order`; `Instructor`, queried three lines
 * above it in the same controller, has `is_active` and `sort_order`. One query
 * shape was copied onto the wrong model, and nothing that ran in CI ever
 * fetched the page.
 *
 * This is the marketing site of a school — the first thing a prospective family
 * sees. A crash here is worse than a crash almost anywhere else in the product.
 *
 * The assertion is deliberately "does not 5xx" rather than "is 200": a page may
 * legitimately 404 on an empty database, but it may never throw.
 */
it('renders every parameterless public page without a server error', function () {
    $checked = [];
    $failures = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if ($name === null || ! str_starts_with($name, 'public.')) {
            continue;
        }
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }
        // Routes needing a model would need fixtures to be meaningful; the
        // parameterless ones are the front door and cost nothing to fetch.
        if ($route->parameterNames() !== []) {
            continue;
        }

        $checked[] = $name;

        // `withoutLocalizationMiddleware()` is load-bearing, not tidiness.
        // These routes sit under a locale prefix, so a plain GET of
        // `route('public.about')` is intercepted by LaravelLocalization's
        // redirect filter and answered with a 302 — never 5xx — and the
        // controller never runs.
        //
        // Two earlier drafts of this test passed against the very bug it was
        // written to catch: first without following redirects, then following
        // them into a page that was not this controller. A guard that cannot
        // fail is worse than no guard, so it is now pinned by the
        // `it fails when a public page throws` test below.
        $status = $this->withoutLocalizationMiddleware()->get(route($name))->getStatusCode();

        if ($status >= 500) {
            $failures[] = $name.'  ('.$route->uri().')  -> '.$status;
        }
    }

    // If the enumeration ever stops finding routes this test would pass by
    // checking nothing at all.
    expect(count($checked))->toBeGreaterThanOrEqual(8, 'Almost no public routes found — the scan has drifted.');

    expect($failures)->toBe(
        [],
        "Public pages that threw. These are the first thing a prospective family sees:\n"
        .implode("\n", $failures)
    );
});

/**
 * The guard above must be able to fail.
 *
 * Two drafts of it passed against the very bug it was written for — once by
 * seeing a 302 from the locale redirect filter, once by following that redirect
 * to a different page. Both looked green and checked nothing. This pins the
 * mechanism itself: a route that throws must be reported, not absorbed by
 * middleware or a redirect.
 */
it('reports a public page that throws', function () {
    Route::middleware('web')->get('/zz-throwing-probe', function () {
        throw new RuntimeException('probe');
    });

    // By URI, not by name: a route registered inside a test body is not in the
    // resolved name lookup that `route()` reads.
    $status = $this->withoutLocalizationMiddleware()
        ->get('/zz-throwing-probe')
        ->getStatusCode();

    expect($status)->toBeGreaterThanOrEqual(500);
});
