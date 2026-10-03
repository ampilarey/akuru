<?php

use App\Support\Navigation\NavigationMap;
use Illuminate\Support\Facades\Route;

/**
 * Every staff screen has a door (ADMIN_PANEL.md §8, the navigation re-audit
 * of 2026-10-03). The owner, walking the panel: "many items are not linked".
 * A machine diff of the GET routes against `NavigationMap::hrefs()` found
 * twelve screens with no way in but their address: the class directory, the
 * promotion wizard, the bank-statement import, the Qur'an oversight report,
 * the Qur'an teacher's assignments and milestones, the Library office's
 * four inner screens, the news categories and the password screen.
 *
 * `AdminPagesAreReachableTest` guards the `/admin/*` landings; this one
 * guards the rest of the office — academics, exams, finance, HR, people,
 * the catalog, circulation and teaching. A screen is reachable when the map
 * names it, or when it is opened from a parent screen named here. A
 * sub-page (a form, a preview, a print view, a redirect) is not a screen of
 * its own and is skipped by its route name.
 */
function staffLandingRoutes(): array
{
    $prefixes = ['academics', 'exams', 'finance', 'hr', 'people', 'catalog', 'circulation', 'teach'];
    $skip = '/\.(export|toggle|save|suggest|store|update|destroy|create|edit|show|import|print|redirect|template|download|pdf|csv|new)$|preview$/';

    return collect(Route::getRoutes())
        ->filter(fn ($r) => $r->getName()
            && in_array('GET', $r->methods(), true)
            && ! preg_match($skip, $r->getName())
            && ! str_contains($r->uri(), '{')
            && in_array(explode('/', $r->uri())[0], $prefixes, true))
        ->mapWithKeys(fn ($r) => [$r->getName() => $r->uri()])
        ->all();
}

// Screens legitimately opened from a parent screen rather than the menu.
// Add here only with the parent named.
function staffScreensOpenedFromAParent(): array
{
    return [
        'academics.attendance.daily' => 'opened from a register (academics.registers.show)',
        'exams.awards.id-card' => 'opened from the awards list (exams.awards.index)',
        'exams.awards.transfer' => 'opened from the awards list (exams.awards.index)',
        'exams.transcript' => 'opened from the report cards screen (exams.report-cards.index), for one student',
    ];
}

it('names every staff screen in the map, or says which parent screen opens it', function () {
    $hrefs = array_map(NavigationMap::path(...), NavigationMap::hrefs());
    $orphans = [];
    $stale = [];
    foreach (staffLandingRoutes() as $name => $uri) {
        $inMap = in_array('/'.$uri, $hrefs, true);
        if (array_key_exists($name, staffScreensOpenedFromAParent())) {
            if ($inMap) {
                $stale[] = "{$name}  (/{$uri})";
            }

            continue;
        }
        if (! $inMap) {
            $orphans[] = "{$name}  (/{$uri})";
        }
    }

    expect($orphans)->toBeEmpty("Staff screens missing from NavigationMap:\n  ".implode("\n  ", $orphans))
        ->and($stale)->toBeEmpty("Staff screens allowlisted as opened from a parent but now in NavigationMap:\n  ".implode("\n  ", $stale));

    // And the allowlist names only routes that exist.
    foreach (array_keys(staffScreensOpenedFromAParent()) as $name) {
        expect(Route::has($name))->toBeTrue("allowlisted route {$name} no longer exists");
    }
});

it('reads the route behind an href with an anchor or a query', function () {
    expect(NavigationMap::path('/admin/bookshop#settings'))->toBe('/admin/bookshop')
        ->and(NavigationMap::path('/academics/registers/today?date=2026-10-03'))->toBe('/academics/registers/today')
        ->and(NavigationMap::path('/admin/users'))->toBe('/admin/users');
});
