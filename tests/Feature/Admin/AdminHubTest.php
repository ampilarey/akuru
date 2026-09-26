<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * `/admin`, the admin panel's front door (docs/ADMIN_PANEL.md §1): the
 * sections a person may open, and only those; nobody who may open none.
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

it('shows a super admin every section, a Bookstore manager only the Bookstore, and refuses a teacher', function () {
    $super = hubUser('super_admin', ['bookshop.manage', 'commerce.manage', 'library.manage', 'prayer.manage', 'pronunciation.manage', 'operations.manage', 'translations.manage']);
    test()->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/AdminHub')
            ->where('t.hub_title', 'Admin panel')
            ->has('sections', 13)
            ->where('sections.0.key', 'ops_checklist')
            ->where('sections.0.hard', false)
            ->where('sections.1.key', 'admin_enrolments')
            ->where('sections.1.hard', true)
            ->where('sections.1.href', '/admin/enrollments')
            ->where('sections.1.description', 'Applications and enrolments: activate, reject, suspend, record a manual payment.'));

    $manager = hubUser('bookshop_manager', ['bookshop.manage']);
    test()->withoutLocalizationMiddleware()->actingAs($manager)->get(route('admin.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->has('sections', 1)->where('sections.0.key', 'bookshop'));

    test()->withoutLocalizationMiddleware()->actingAs(hubUser('teacher'))->get(route('admin.index'))->assertForbidden();
    app('auth')->forgetGuards();
    test()->withoutLocalizationMiddleware()->get(route('admin.index'))->assertRedirect();
});

it('is linked from both menus, in Dhivehi too', function () {
    $admin = hubUser('admin', ['operations.manage']);
    test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.enrollments.index'))->assertOk()
        ->assertSee('Admin panel')->assertSee(route('admin.index'));
    app()->setLocale('dv');
    $nav = app(\App\Support\Navigation\BuildNavigationAction::class)->execute($admin, 'dv');
    app()->setLocale('en');
    $group = collect($nav['groups'])->firstWhere('key', 'admin_group');
    expect($group['items'][0]['href'])->toBe('/admin')->and($group['items'][0]['label'])->toBe('އެޑްމިން ޕެނަލް');
    // A teacher is not offered the door they cannot open.
    $teacherNav = app(\App\Support\Navigation\BuildNavigationAction::class)->execute(hubUser('teacher'), 'en');
    expect(collect($teacherNav['groups'])->firstWhere('key', 'admin_group'))->toBeNull();
});
