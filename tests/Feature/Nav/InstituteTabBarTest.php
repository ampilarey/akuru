<?php

use App\Domains\Identity\Models\User;
use App\Support\Navigation\BuildNavigationAction;
use App\Support\Navigation\WorkspaceMap;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * The Institute's phone tab bar (ADMIN_PANEL.md §7 M7, C15 slice 7, STATUS
 * §5nu): Home, a tab per part of the panel, Alerts. The server names the
 * tabs (`nav.tabs`) so the shell renders what it is given, the same way the
 * bar and the groups reach it; a tab is only offered where its group
 * survived the route gates, so it can never open onto an empty sheet.
 */
function tabBarPerson(array $roles, array $permissions = []): User
{
    test()->seed(RoleSeeder::class);
    $user = User::factory()->create();
    foreach ($roles as $role) {
        $user->assignRole(Role::findOrCreate($role, 'web'));
    }
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

it('names four of the Institute\'s parts as tabs, each one a group the person was given', function () {
    // Admissions is a card on the home and not a tab (ADMIN_PANEL.md §8):
    // six tabs is the most a 390 px phone carries.
    $nav = app(BuildNavigationAction::class)->execute(tabBarPerson(['super_admin'], ['bookshop.manage', 'commerce.manage', 'library.manage']), 'en', 'institute');

    expect(array_column($nav['tabs'], 'key'))->toBe(['panel_website', 'panel_money', 'panel_settings', 'panel_system'])
        ->and(array_column($nav['tabs'], 'label'))->toBe(['Website', 'Shops', 'Settings', 'System'])
        ->and(array_column($nav['groups'], 'key'))->toContain('panel_website', 'panel_admissions', 'panel_money', 'panel_settings', 'panel_system');
});

it('gives the tabs short labels in Dhivehi and Arabic too', function () {
    $user = tabBarPerson(['super_admin']);

    foreach (['dv' => 'ފިހާރަތައް', 'ar' => 'المتاجر'] as $locale => $shops) {
        app()->setLocale($locale);
        $nav = app(BuildNavigationAction::class)->execute($user, $locale, 'institute');
        expect(collect($nav['tabs'])->firstWhere('key', 'panel_money')['label'])->toBe($shops);
    }
    app()->setLocale('en');
});

it('gives the School, a family and a shop no tabs — their menus are a drawer', function () {
    expect(WorkspaceMap::tabsFor('school'))->toBe([])
        ->and(WorkspaceMap::tabsFor('family'))->toBe([])
        ->and(WorkspaceMap::tabsFor('vendor'))->toBe([]);

    $nav = app(BuildNavigationAction::class)->execute(tabBarPerson(['admin']), 'en', 'school');
    expect($nav['tabs'])->toBe([]);
});

it('leaves a tab out when its group fell to the gates', function () {
    // A system admin stripped of every permission still holds the role, and
    // the `role:super_admin` routes (users, settings) still admit them — but
    // the money group's doors are all `can:`-gated on abilities they lack.
    Role::findOrCreate('super_admin', 'web')->syncPermissions([]);
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $nav = app(BuildNavigationAction::class)->execute($user, 'en', 'institute');
    $groups = array_column($nav['groups'], 'key');

    expect(array_column($nav['tabs'], 'key'))->toBe(array_values(array_intersect(['panel_website', 'panel_money', 'panel_settings', 'panel_system'], $groups)));
});

it('reaches the shell on every Institute page as a shared prop', function () {
    $this->withoutLocalizationMiddleware();

    $this->actingAs(tabBarPerson(['super_admin'], ['bookshop.manage', 'commerce.manage', 'library.manage']))
        ->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.tabs.0.key', 'panel_website')
            ->where('nav.tabs.2.label', 'Settings')
            ->where('nav.tabs.3.label', 'System')
            ->where('i18n.nav.tab_bar', 'Sections')
            ->etc());
});
