<?php

use App\Domains\Identity\Models\User;
use App\Support\Authorization\RoleLabels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The role labels people read (ADR-040 slice 3, STATUS §5if). The owner's
 * names for the jobs — System admin, Educational admin, Dean — replace the
 * humanised keys ("Super Admin", "Admin", "Headmaster") wherever a role is
 * shown: the users screen, the Blade user menu, the linked-accounts list,
 * the staff form. The keys in the database do not change.
 */
function labelledUser(string $role, string $name = 'Someone'): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->assignRole(Role::findOrCreate($role, 'web'));

    return $user->fresh();
}

it('names every role in the three languages, and humanises one it does not know', function () {
    foreach (RoleLabels::KNOWN as $role) {
        foreach (['en', 'dv', 'ar'] as $locale) {
            expect(trans('roles.'.$role, [], $locale))->not->toBe('roles.'.$role, "{$role} in {$locale}");
        }
    }
    expect(RoleLabels::label('super_admin'))->toBe('System admin')
        ->and(RoleLabels::label('admin'))->toBe('Educational admin')
        ->and(RoleLabels::label('headmaster'))->toBe('Dean')
        ->and(RoleLabels::label('bookshop_manager'))->toBe('Bookstore admin')
        ->and(RoleLabels::label('headmaster', 'dv'))->toBe('ޑީން')
        ->and(RoleLabels::label('admin', 'ar'))->toBe('المدير التعليمي')
        ->and(RoleLabels::label('night_watch'))->toBe('Night Watch')
        ->and(RoleLabels::list(['teacher', 'parent']))->toBe('Teacher, Parent')
        ->and(RoleLabels::list([]))->toBe('No role')
        ->and(array_keys(RoleLabels::all()))->toBe(RoleLabels::KNOWN);
});

it('labels a person by their first role, and still spots a login named after it', function () {
    expect(labelledUser('admin')->primaryRoleLabel())->toBe('Educational admin')
        ->and(labelledUser('headmaster')->primaryRoleLabel())->toBe('Dean')
        ->and(User::factory()->create()->primaryRoleLabel())->toBeNull();

    // The seeded "System Admin" and the old "Super Admin" both read as the role.
    expect(labelledUser('super_admin', 'System Admin')->nameDuplicatesPrimaryRole())->toBeTrue()
        ->and(labelledUser('super_admin', 'Super Admin')->nameDuplicatesPrimaryRole())->toBeTrue()
        ->and(labelledUser('super_admin', 'Aishath Ali')->nameDuplicatesPrimaryRole())->toBeFalse();
});

it('shows the labels on the users screen, its filter and the Blade user menu', function () {
    $super = labelledUser('super_admin', 'Aishath Ali');
    labelledUser('admin', 'Office Admin');
    labelledUser('headmaster', 'The Dean');

    // Since C9 slice 2 (STATUS §5jd) the users screen is an Inertia page: the
    // labels are its props, and so is the filter's list of roles.
    $page = test()->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.users.index'))->assertOk()
        ->viewData('page');
    $labels = array_column($page['props']['users'], 'role_label');
    expect($labels)->toContain('Educational admin', 'Dean', 'System admin')
        ->not->toContain('Super Admin', 'Headmaster');
    // Every role is offered by the filter, by label.
    $roles = collect($page['props']['roles'])->pluck('label', 'key');
    expect($roles['headmaster'])->toBe('Dean')->and($roles['bookshop_manager'])->toBe('Bookstore admin');

    // The filter still works on the key.
    $filtered = test()->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.users.index', ['role' => 'headmaster']))->assertOk()->viewData('page');
    expect(array_column($filtered['props']['users'], 'name'))->toBe(['The Dean']);

    // The Blade user menu names the role under the person, on a School screen too.
    $admin = User::query()->where('name', 'Office Admin')->sole();
    $menu = test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('quran-progress.index'))->assertOk()->getContent();
    expect(substr_count($menu, 'Educational admin'))->toBeGreaterThanOrEqual(2);
});

it('labels the staff form’s roles and the linked accounts', function () {
    $admin = labelledUser('admin');
    test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('people.staff.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('roles', ['teacher' => 'Teacher', 'supervisor' => 'Supervisor', 'headmaster' => 'Dean', 'admin' => 'Educational admin']));

    $teacherParent = labelledUser('teacher');
    $teacherParent->assignRole(Role::findOrCreate('parent', 'web'));
    $nobody = User::factory()->create();
    \App\Domains\Identity\Models\LinkedAccount::query()->create(['user_id' => $admin->id, 'linked_user_id' => $teacherParent->id, 'verified_at' => now()]);
    \App\Domains\Identity\Models\LinkedAccount::query()->create(['user_id' => $admin->id, 'linked_user_id' => $nobody->id, 'verified_at' => now()]);

    $list = app(\App\Domains\Identity\Actions\ListLinkedAccountsAction::class)->execute((int) $admin->id)->keyBy('id');
    expect($list[$teacherParent->id]['roles'])->toBe('Teacher, Parent')
        ->and($list[$nobody->id]['roles'])->toBe('No role');
});
