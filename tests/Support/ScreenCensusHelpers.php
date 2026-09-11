<?php

use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * The census of screens, and the one place that decides what a screen is.
 *
 * `StaffScreensDoNotCrashTest` and `PortalScreensDoNotCrashTest` were each
 * built from their own hand-written list of URI prefixes. Both lists were
 * mine, and between them they **missed forty authenticated screens**:
 * `students`, `teachers`, `substitutions/*`, `quran-progress`, all seventeen
 * `hifz/*` pages, `notifications`, `my-enrollments`, `review`, `write`,
 * `forms`, `e-learning/*` and `dashboard` itself.
 *
 * Nothing was wrong with either test. The **shape** was wrong: an allow-list
 * of prefixes only covers what someone remembered to write down, and it fails
 * silently — a sweep reports what it looked at, never what it forgot. Adding a
 * new route group to the app coveres it in no guard and says nothing.
 *
 * So the allow-list is inverted here. This file enumerates **every**
 * parameterless GET route, subtracts the handful that are genuinely not
 * screens (each named, with a reason), and the guards split what remains:
 * family-facing screens to the portal guard, **everything else** to the staff
 * guard. A new route group is therefore swept the day it is added, by nobody's
 * remembering.
 *
 * The prefix bug that started this is fixed here too: matching `teach` as a
 * bare string also matches `teachers/`, so the staff teacher CRUD was being
 * swept as a family screen. `underPrefix()` requires a segment boundary.
 */

/**
 * Is `$uri` at or beneath `$prefix`, on a path-segment boundary?
 *
 * `underPrefix('teachers/create', 'teach')` is **false** — `teach` and
 * `teachers` are different route groups. Plain `str_starts_with` said true,
 * which is how staff screens ended up in the family guard.
 */
function underPrefix(string $uri, string $prefix): bool
{
    $prefix = rtrim($prefix, '/');

    return $uri === $prefix || str_starts_with($uri, $prefix.'/');
}

/**
 * Routes that exist but are not screens a person looks at. Each one is listed
 * with the reason it is exempt, because an unexplained exemption is how a real
 * screen goes missing.
 *
 * @return array<string, string> uri or prefix => reason
 */
function nonScreenRoutes(): array
{
    return [
        'api' => 'JSON for the mobile scaffold and the prayer-times widget, not a page',
        'up' => 'Laravel health endpoint',
        'sw.js' => 'PWA service worker',
        'offline.html' => 'PWA offline shell, served as a static file',
        'manifest.webmanifest' => 'PWA manifest',
        'robots.txt' => 'crawler directives',
        'sitemap.xml' => 'crawler sitemap',
        'inertia-test' => 'the Inertia smoke route, already pinned by InertiaSmokeRouteTest',
        'en' => 'locale root; LaravelLocalization redirects it to the homepage',
        'dv' => 'locale root',
        'ar' => 'locale root',
    ];
}

/** Family- and teacher-facing prefixes: the portal guard's half. */
function familyPrefixes(): array
{
    return ['portal', 'learn', 'teach'];
}

/**
 * Screens whose response body is a file rather than a page. Fetching one in a
 * sweep proves nothing and costs a render.
 */
function nonPageSegments(): array
{
    return ['export', 'photo', 'file', 'download', 'csv', 'pdf', 'print'];
}

/**
 * Every parameterless GET screen in the app.
 *
 * Routes carrying a `{parameter}` need a real row to point at and are not
 * covered here — that is a known, named gap rather than an accidental one.
 *
 * @return list<array{0: string, 1: string}> name + uri
 */
function allScreens(): array
{
    $screens = [];

    foreach (RouteFacade::getRoutes() as $route) {
        $uri = $route->uri();
        $name = $route->getName() ?? '';

        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }

        if ($route->parameterNames() !== []) {
            continue;
        }

        if (collect(nonPageSegments())->contains(fn (string $s): bool => str_contains($uri, $s))) {
            continue;
        }

        // The marketing site has its own guard, written after two of its pages
        // returned 500 to every visitor for a week.
        if (str_starts_with($name, 'public.')) {
            continue;
        }

        if (collect(array_keys(nonScreenRoutes()))->contains(fn (string $p): bool => underPrefix($uri, $p))) {
            continue;
        }

        $screens[] = [$name, $uri];
    }

    return array_values(array_unique($screens, SORT_REGULAR));
}

/** The family half: `portal/`, `learn`, `teach/`. */
function familyScreens(): array
{
    return array_values(array_filter(
        allScreens(),
        fn (array $s): bool => collect(familyPrefixes())->contains(fn (string $p): bool => underPrefix($s[1], $p))
    ));
}

/**
 * Everything else — staff screens, the authenticated odds and ends, and the
 * auth flows. Defined by subtraction on purpose: there is no list to forget to
 * add to.
 */
function staffScreens(): array
{
    return array_values(array_filter(
        allScreens(),
        fn (array $s): bool => ! collect(familyPrefixes())->contains(fn (string $p): bool => underPrefix($s[1], $p))
    ));
}
