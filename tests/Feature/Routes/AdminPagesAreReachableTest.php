<?php

use App\Domains\Identity\Models\User;
use App\Support\Navigation\NavigationMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * A page nobody can navigate to is not shipped.
 *
 * The operator checklist and feature walkthrough were built, permissioned and
 * CI-green, but linked only from the Inertia AppShell — which a Blade landing
 * never rendered — so an admin could not find them. Commerce, Library and
 * Pronunciation had no inbound link from any view, and the prayer-times pages
 * only linked to each other. Since STATUS §5id both shells render the one
 * navigation map, so the checks are: every admin landing is in the map, and
 * the Blade shell really renders the map rather than a hand-written list.
 */
function adminLandingRoutes(): array
{
    $skip = '/\.(export|toggle|save|suggest|store|update|destroy|create|edit|show|preview|import)$/';

    return collect(Route::getRoutes())
        ->filter(fn ($r) => $r->getName()
            && str_starts_with($r->getName(), 'admin.')
            && in_array('GET', $r->methods(), true)
            && ! preg_match($skip, $r->getName())
            && ! str_contains($r->uri(), '{'))
        ->mapWithKeys(fn ($r) => [$r->getName() => $r->uri()])
        ->all();
}

// Pages legitimately opened from a parent screen rather than the menu.
// Add here only with the parent named.
function adminPagesOpenedFromAParent(): array
{
    return [
        'admin.prayer-times.groups.index' => 'opened from the admin.prayer-times.islands hub',
        'admin.prayer-times.broadcasts.index' => 'opened from the admin.prayer-times.islands hub',
        'admin.daily-content.queue' => 'opened from admin.daily-content.index',
        'admin.daily-content.ayah-preview' => 'opened from admin.daily-content.index',
        'admin.enrollments.payments' => 'opened from admin.enrollments.index',
        'admin.leads.index' => 'opened from the Website CMS hub (admin.pages.index)',
        'admin.funnel.index' => 'opened from the Website CMS hub (admin.pages.index)',
        'admin.daily-content.index' => 'opened from the Website CMS hub (admin.pages.index)',
        'admin.daily-subscriptions.index' => 'opened from the Website CMS hub (admin.pages.index)',
        'admin.courses.index' => 'opened from the Website CMS hub (admin.pages.index)',
        // Deliberately not in the menu: a list that accuses readers of
        // theft should take a decision to open, not sit in a nav bar.
        'admin.library.reading-alerts' => 'opened from the Library admin hub (admin.library.index)',
        // B12: the money rules, behind a Settings button on the same hub.
        'admin.library.settings' => 'opened from the Library admin hub (admin.library.index)',
        'admin.library.insights' => 'opened from the Library admin hub (admin.library.index)',
        'admin.library.promotions' => 'opened from the Library admin hub (admin.library.index)',
        // Same call as the reading alerts: a security log naming contacts that
        // have been refused should be opened deliberately, not sat in a menu.
        'admin.users.otp-abuse' => 'opened from User management (admin.users.index)',
        'admin.courses.deleted' => 'opened from Manage Courses (admin.courses.index)',
        // The workspace homes themselves (STATUS §5id).
        'admin.index' => 'the Institute home: the wordmark, Dashboard and the switcher',
    ];
}

it('names every admin landing page in the map, Blade screens marked for a full page load', function () {
    $hrefs = NavigationMap::hrefs();
    $orphans = [];
    foreach (adminLandingRoutes() as $name => $uri) {
        if (array_key_exists($name, adminPagesOpenedFromAParent()) || in_array('/'.$uri, $hrefs, true)) {
            continue;
        }
        $orphans[] = "{$name}  (/{$uri})";
    }

    expect($orphans)->toBeEmpty("Admin pages missing from NavigationMap (both shells render it):\n  ".implode("\n  ", $orphans));

    // Every Blade admin screen in the map is marked `hard`; every Inertia one
    // is not — the sections and the screens inside them alike. Since C9
    // slice 12 (STATUS §5jn) there is no Blade admin screen left, so the
    // list is empty and the loop asserts that nothing is marked.
    $blade = [];
    foreach ([...NavigationMap::groups(), ...array_map(fn ($items, $bar) => ['key' => $bar, 'items' => $items], NavigationMap::primary(), array_keys(NavigationMap::primary()))] as $group) {
        foreach ($group['items'] as $item) {
            foreach ([$item, ...($item['children'] ?? [])] as $entry) {
                if (str_starts_with($entry['href'], '/admin/')) {
                    expect(! empty($entry['hard']))->toBe(in_array($entry['href'], $blade, true), $entry['href']);
                }
            }
        }
    }
});

it('renders the Blade nav from the map rather than by hand, in the More menu and the phone menu alike', function () {
    // Two real bugs hid behind a hand-written nav: pages linked only from the
    // Inertia shell, and a phone menu that stopped at the CMS (STATUS §5ht).
    $nav = (string) file_get_contents(resource_path('views/layouts/navigation.blade.php'));

    expect($nav)->toContain('ResolveWorkspacesAction')->toContain('BuildNavigationAction')
        ->and(preg_match("/route\\('admin\\./", $nav))->toBe(0, 'a hand-written admin link survives in the Blade nav')
        ->and(substr_count($nav, "@foreach(\$nav['groups'] as \$group)"))->toBe(2)
        ->and(substr_count($nav, "@foreach(\$nav['primary'] as \$item)"))->toBe(2);

    $mobile = substr($nav, strpos($nav, 'data-testid="mobile-menu"'));
    expect($mobile)->toContain("@foreach(\$nav['groups'] as \$group)")->toContain("data-nav-section=\"{{ \$group['key'] }}\"");

    // And the menus say what they are to a screen reader.
    expect($nav)->toContain(':aria-expanded="adminOpen"')->toContain(':aria-expanded="open"')->toContain('aria-controls="nav-mobile-menu"')->toContain('href="#main"');
});

it('reaches every admin landing page from a Blade screen, as the role that runs it', function () {
    // The map says a page is there; this opens a Blade screen as the system
    // admin (the Institute) and as the educational admin (the School) and
    // reads the rendered menus, so a gate the map does not know about cannot
    // hide a page quietly.
    $seed = function (string $role, array $permissions): User {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'web'));
        $user->givePermissionTo($permissions);

        return $user;
    };
    // The Institute's Blade shell: the system admin has no Blade screen of
    // their own since C9 slice 13 (every admin page and both dashboards are
    // Inertia), but the Blade nav shows them the Institute on any Blade
    // screen they may open — the substitution requests list here.
    $institute = $this->withoutLocalizationMiddleware()->actingAs($seed('super_admin', ['bookshop.manage', 'commerce.manage', 'library.manage', 'prayer.manage', 'pronunciation.manage', 'operations.manage', 'translations.manage']))
        ->get(route('substitutions.requests.index'))->assertOk()->getContent();
    // The School's Blade shell: the Quran progress list, since the enrolment lists are Inertia (C9 slice 4).
    $school = $this->withoutLocalizationMiddleware()->actingAs($seed('admin', ['registers.manage', 'exams.manage']))
        ->get(route('quran-progress.index'))->assertOk()->getContent();

    $unreachable = [];
    foreach (adminLandingRoutes() as $name => $uri) {
        if (array_key_exists($name, adminPagesOpenedFromAParent())) {
            continue;
        }
        if (! str_contains($institute, '/'.$uri.'"') && ! str_contains($school, '/'.$uri.'"')) {
            $unreachable[] = "{$name}  (/{$uri})";
        }
    }

    expect($unreachable)->toBeEmpty("Admin pages in neither the Institute's nor the School's Blade menus:\n  ".implode("\n  ", $unreachable));
});
