<?php

use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * The census of screens, and the one place that decides what a screen is.
 *
 * `StaffScreensDoNotCrashTest` and `PortalScreensDoNotCrashTest` were each
 * built from their own hand-written list of URI prefixes. Both lists were
 * mine, and between them they **missed sixty-five authenticated screens**:
 * `students`, `teachers`, `substitutions/*`, `quran-progress`, all seventeen
 * `hifz/*` pages, `notifications`, `my-enrollments`, `review`, `write`,
 * `forms`, `e-learning/*` and `dashboard` itself.
 *
 * Nothing was wrong with either test. The **shape** was wrong: an allow-list
 * of prefixes only covers what someone remembered to write down, and it fails
 * silently — a sweep reports what it looked at, never what it forgot. A route
 * group added to the app lands in no guard, and nothing says so.
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
 * Routes carrying a `{parameter}` need a real row to point at, and are covered
 * separately by `detailScreens()` and `DetailScreensDoNotCrashTest`.
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

/**
 * Detail screens: routes that show one record, named by a `{parameter}`.
 *
 * These are the pages that actually display data, so they are the ones most
 * likely to fall over on a null relation — and until now nothing loaded any of
 * them. `allScreens()` drops every route with a parameter; this is that half.
 *
 * Two kinds of route are excluded here, and the reason matters:
 *
 * - **File responses.** `hr/payslips/{payslip}/document` and
 *   `finance/receipts/{receipt}/document` stream a stored document, and
 *   `catalog/media/{media}` streams catalog media with an inline
 *   `Content-Disposition`. None of them renders a page. The `nonPageSegments()`
 *   filter does not catch these, because their URIs say "document" and "media"
 *   rather than "download" — so they are named here explicitly. Each was read
 *   before being excluded, not guessed from its name.
 * - **Not screens at all.** `storage/{path}` serves files, `locale/{locale}`
 *   switches language and redirects, and `verify-email/{id}/{hash}` is a
 *   signed link that consumes its own token.
 *
 * @return list<array{0: string, 1: string, 2: list<string>}> name, uri, parameter names
 */
function detailScreens(): array
{
    $notPages = [
        'hr/payslips/{payslip}/document' => 'streams a stored document, not a page',
        'finance/receipts/{receipt}/document' => 'streams a stored document, not a page',
        'catalog/media/{media}' => 'streams catalog media inline, not a page',
        'circulation/barcode/{value}' => 'returns image/svg+xml — an image endpoint, not a page. The labels screen renders these barcodes inline and is swept instead.',
        'learn/media/{media}' => 'streams catalog media inline through the same action as catalog/media, not a page',
        'storage/{path}' => 'serves files from disk',
        'locale/{locale}' => 'switches language and redirects',
        'verify-email/{id}/{hash}' => 'signed link that consumes its own token',
    ];

    $screens = [];

    foreach (RouteFacade::getRoutes() as $route) {
        $uri = $route->uri();
        $name = $route->getName() ?? '';

        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }

        if ($route->parameterNames() === []) {
            continue;
        }

        if (collect(nonPageSegments())->contains(fn (string $s): bool => str_contains($uri, $s))) {
            continue;
        }

        if (str_starts_with($name, 'public.') || str_starts_with($uri, 'api/')) {
            continue;
        }

        if (array_key_exists($uri, $notPages)) {
            continue;
        }

        // Family-facing detail screens belong to
        // `FamilyDetailScreensDoNotCrashTest`, which sweeps them as an enrolled
        // student rather than as a super_admin. Splitting them out here mirrors
        // how `allScreens()` splits the parameterless half, and keeps each test
        // asserting about the audience it can actually speak for.
        if (collect(familyPrefixes())->contains(fn (string $p): bool => underPrefix($uri, $p))) {
            continue;
        }

        $screens[] = [$name, $uri, $route->parameterNames()];
    }

    return array_values(array_unique($screens, SORT_REGULAR));
}

/**
 * The Eloquent class each parameter of a route is bound to, read off the
 * controller's own signature.
 *
 * A route whose controller type-hints `ClassRoom $classRoom` tells us exactly
 * which table to build a row in; one that takes `int $course` does not, and is
 * reported as unresolved rather than quietly skipped. Nothing is inferred from
 * the parameter's *name* — `{session}` means a Quran session on one route and
 * an offering session on another, and guessing between them by name is how a
 * guard ends up asserting nothing.
 *
 * @return array<string, class-string> parameter name => model class
 */
function routeModelBindings(\Illuminate\Routing\Route $route): array
{
    $bindings = [];

    try {
        foreach ($route->signatureParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $class = $type->getName();

            if (is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) {
                $bindings[$parameter->getName()] = $class;
            }
        }
    } catch (Throwable) {
        // A route with an unresolvable action signature simply yields no
        // bindings, and is reported as unresolved by the caller.
        return [];
    }

    return $bindings;
}
