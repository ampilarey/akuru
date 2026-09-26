<?php

use App\Domains\Identity\Models\User;
use App\Support\Navigation\BuildNavigationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * `/admin`, the admin panel's front door (docs/ADMIN_PANEL.md §1): the
 * panel in four parts — Admissions, Website & content, Shops & money,
 * System — the sections a person may open and the screens inside them,
 * and only those; nobody who may open none.
 */
function hubUser(string $role, array $permissions = []): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

const HUB_PERMISSIONS = ['bookshop.manage', 'commerce.manage', 'library.manage', 'prayer.manage', 'pronunciation.manage', 'operations.manage', 'translations.manage'];

it('shows a super admin the four parts with every section and its screens, a Bookstore manager one part with one section, and refuses a teacher', function () {
    $super = hubUser('super_admin', HUB_PERMISSIONS);
    test()->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/AdminHub')
            ->where('t.hub_title', 'Admin panel')
            ->has('parts', 4)
            ->where('parts.0.key', 'panel_admissions')->where('parts.0.label', 'Admissions')->has('parts.0.sections', 2)
            ->where('parts.1.key', 'panel_website')->where('parts.1.label', 'Website & content')->has('parts.1.sections', 3)
            ->where('parts.2.key', 'panel_money')->where('parts.2.label', 'Shops & money')->has('parts.2.sections', 3)
            ->where('parts.3.key', 'panel_system')->where('parts.3.label', 'System')->has('parts.3.sections', 5)
            // A section: where it goes, how it opens, what it is for, and what is inside.
            ->where('parts.0.sections.0.key', 'admin_enrolments')
            ->where('parts.0.sections.0.href', '/admin/enrollments')
            ->where('parts.0.sections.0.hard', true)
            ->where('parts.0.sections.0.description', 'Applications and enrolments: activate, reject, suspend, record a manual payment.')
            ->where('parts.0.sections.0.children.0.label', 'Payments')
            ->where('parts.0.sections.0.children.0.href', '/admin/enrollments/payments')
            ->where('parts.0.sections.0.children.0.hard', true)
            ->where('parts.1.sections.0.key', 'website_cms')->has('parts.1.sections.0.children', 8)
            ->where('parts.1.sections.0.children.7.label', 'Funnel')
            ->where('parts.1.sections.1.key', 'prayer_times')->has('parts.1.sections.1.children', 4)
            ->where('parts.1.sections.1.children.3.href', '/admin/prayer-times/import')
            ->where('parts.2.sections.0.key', 'commerce')->where('parts.2.sections.0.hard', false)->has('parts.2.sections.0.children', 0)
            ->where('parts.3.sections.0.key', 'manage_users')->where('parts.3.sections.1.key', 'system_settings'));

    // Thirteen sections in all, as before the grouping.
    $parts = app(\App\Domains\Settings\Actions\ListAdminSectionsAction::class)->execute($super, 'en');
    expect(array_sum(array_map(fn ($part) => count($part['sections']), $parts)))->toBe(13);

    $manager = hubUser('bookshop_manager', ['bookshop.manage']);
    test()->withoutLocalizationMiddleware()->actingAs($manager)->get(route('admin.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('parts', 1)->where('parts.0.key', 'panel_money')->has('parts.0.sections', 1)->where('parts.0.sections.0.key', 'bookshop'));

    test()->withoutLocalizationMiddleware()->actingAs(hubUser('teacher'))->get(route('admin.index'))->assertForbidden();
    app('auth')->forgetGuards();
    test()->withoutLocalizationMiddleware()->get(route('admin.index'))->assertRedirect();
});

it('leaves out a part, a section and an inner screen the person could only be refused', function () {
    // An admin without prayer.manage: Website & content is there for the CMS,
    // without the prayer-times section; System has no Users or Settings.
    $admin = hubUser('admin', ['operations.manage']);
    $parts = collect(app(\App\Domains\Settings\Actions\ListAdminSectionsAction::class)->execute($admin, 'en'))->keyBy('key');
    expect($parts->keys()->all())->toBe(['panel_admissions', 'panel_website', 'panel_system'])
        ->and(collect($parts['panel_website']['sections'])->pluck('key')->all())->toBe(['website_cms'])
        ->and(collect($parts['panel_system']['sections'])->pluck('key')->all())->toBe(['ops_checklist', 'feature_walkthrough']);

    // Every inner screen is a real route behind its own gate, and every
    // Blade one is marked for a full page load.
    $routes = \Illuminate\Support\Facades\Route::getRoutes()->getRoutesByMethod()['GET'];
    foreach (\App\Support\Navigation\NavigationMap::adminPanel() as $item) {
        foreach ($item['children'] ?? [] as $child) {
            expect(isset($routes[ltrim($child['href'], '/')]))->toBeTrue("no GET route for {$child['href']}");
            expect(str_starts_with($child['href'], $item['href'] === '/admin/public-site/pages' ? '/admin/public-site' : dirname($item['href'])))->toBeTrue("{$child['href']} is not inside {$item['href']}");
        }
    }
});

it('heads the shell’s admin column and both Blade menus with the same four parts, in Dhivehi too', function () {
    $admin = hubUser('admin', HUB_PERMISSIONS);

    // The Inertia shell: each admin item carries its part, so the column can
    // head it; the front door carries none.
    $nav = app(BuildNavigationAction::class)->execute($admin, 'en');
    $items = collect(collect($nav['groups'])->firstWhere('key', 'admin_group')['items']);
    expect($items[0]['key'])->toBe('admin_home')->and($items[0])->not->toHaveKey('section')
        ->and($items->slice(1)->pluck('section.label')->unique()->values()->all())->toBe(['Admissions', 'Website & content', 'Shops & money', 'System'])
        ->and($items->firstWhere('key', 'website_cms')['children'])->toHaveCount(8)
        ->and($items->firstWhere('key', 'website_cms')['children'][0])->toBe(['key' => 'cms_pages', 'label' => 'Pages', 'href' => '/admin/public-site/pages', 'hard' => true])
        ->and($items->firstWhere('key', 'commerce'))->not->toHaveKey('children');

    // The Blade menus: the headings, the front door, and the panel under them.
    $html = test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.enrollments.index'))->assertOk()
        ->assertSee('Admin panel')->assertSee(route('admin.index'))
        ->assertSee('data-nav-section="panel_admissions"', false)
        ->assertSee('data-nav-section="panel_website"', false)
        ->assertSee('data-nav-section="panel_money"', false)
        ->assertSee('data-nav-section="panel_system"', false)
        ->getContent();
    // Each heading twice: the desktop dropdown and the mobile menu.
    expect(substr_count($html, 'data-nav-section="panel_money"'))->toBe(2);
    // A plain admin is not offered the System heading's super-admin entries.
    expect($html)->not->toContain(route('admin.users.index'));

    app()->setLocale('dv');
    $dv = collect(collect(app(BuildNavigationAction::class)->execute($admin, 'dv')['groups'])->firstWhere('key', 'admin_group')['items']);
    app()->setLocale('en');
    expect($dv[0]['href'])->toBe('/admin')->and($dv[0]['label'])->toBe('އެޑްމިން ޕެނަލް')
        ->and($dv->firstWhere('key', 'website_cms')['section']['label'])->toBe('ވެބްސައިޓާއި ކޮންޓެންޓް')
        ->and($dv->firstWhere('key', 'website_cms')['children'][0]['label'])->toBe('ސަފުހާތައް');

    // A teacher is not offered the door they cannot open.
    $teacherNav = app(BuildNavigationAction::class)->execute(hubUser('teacher'), 'en');
    expect(collect($teacherNav['groups'])->firstWhere('key', 'admin_group'))->toBeNull();
});
