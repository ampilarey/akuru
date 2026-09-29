<?php

use App\Domains\Identity\Models\User;
use App\Support\Authorization\RoleGrants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The educational admin's permission set and the tightened gates (ADR-040
 * slice 2, STATUS §5ie). `admin` used to hold `Permission::all()`, identical
 * to `super_admin` (KNOWN_ISSUES 10): since the workspaces the menus hid the
 * Institute from an educational admin, and the routes still admitted them.
 * Now the set is a decision — the school's office, the academics read only,
 * nothing of the website, the shops, the library office or the system — and
 * the Institute's routes admit the system admin alone.
 */
function seededRoleUser(string $role): User
{
    test()->seed(\Database\Seeders\RoleSeeder::class);
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function permissionAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

it('creates the admin role with exactly its set by migration, so a migrate-only database gets it', function () {
    // No seeder has run: the role and every permission in the set came from
    // `2026_09_27_000001`, and the role holds the set and nothing more.
    $admin = Role::query()->where('name', 'admin')->where('guard_name', 'web')->first();
    expect($admin)->not->toBeNull();

    $held = $admin->permissions()->pluck('name')->sort()->values()->all();
    $set = collect(RoleGrants::educationalAdmin())->sort()->values()->all();
    expect($held)->toBe($set);

    // Every permission the set names exists (a `can()` on a missing row is
    // false for everyone), and the system admin holds each of them.
    foreach ($set as $permission) {
        expect(Permission::query()->where('name', $permission)->where('guard_name', 'web')->exists())->toBeTrue($permission);
    }
    $super = User::factory()->create();
    $super->assignRole('super_admin');
    foreach (['finance.manage', 'payroll.approve', 'payments.refund', 'requests.review'] as $permission) {
        expect($super->fresh()->can($permission))->toBeTrue($permission);
    }
});

it('seeds the admin role to the same set, removing what a blanket grant gave it', function () {
    // The seeder used to grant everything; a re-seed must take that away, or
    // a deployment that once seeded keeps the old grant beside the new gates.
    Role::findOrCreate('admin', 'web')->givePermissionTo(Permission::all());
    test()->seed(\Database\Seeders\RoleSeeder::class);

    $held = Role::findByName('admin')->permissions()->pluck('name')->sort()->values()->all();
    expect($held)->toBe(collect(RoleGrants::educationalAdmin())->sort()->values()->all());

    // The set is what the owner decided: the office and the money, the
    // academics read only, nothing of the Institute.
    $admin = seededRoleUser('admin');
    foreach (['finance.manage', 'finance.record-manual-payment', 'payments.record', 'payments.refund', 'hr.manage', 'payroll.run', 'payroll.approve',
        'registers.manage', 'requests.review', 'calendar.manage', 'events.manage', 'rooms.manage', 'meetings.manage', 'messages.broadcast', 'forms.manage',
        'custom_fields.manage', 'students.view-sensitive', 'view_grades', 'view_attendance', 'view_timetables', 'generate_reports'] as $permission) {
        expect($admin->can($permission))->toBeTrue("the educational admin should hold {$permission}");
    }
    foreach (['exams.manage', 'exams.enter-any', 'manage_grades', 'mark_attendance', 'manage_attendance', 'manage_timetables', 'manage_classes', 'courses.manage', 'courses.publish',
        'manage_hifz_programs', 'approve_hifz_milestones', 'view_hifz_programs', 'view_hifz_reports', 'behavior.manage', 'registers.fill', 'daily_content.manage', 'prayer.manage',
        'commerce.manage', 'library.manage', 'bookshop.manage', 'translations.manage', 'operations.manage', 'pronunciation.manage', 'sensitive.read', 'manage_school', 'delete_users'] as $permission) {
        expect($admin->can($permission))->toBeFalse("the educational admin should not hold {$permission}");
    }
});

it('admits the educational admin to the school office and refuses them the Institute', function () {
    $admin = seededRoleUser('admin');

    foreach (['admin.enrollments.index', 'admin.enrollments.payments', 'people.students.index', 'people.staff.index', 'finance.invoices.index', 'hr.payroll.index', 'academics.years.index', 'announcements.index', 'school.index'] as $route) {
        permissionAs($admin)->get(route($route))->assertOk();
    }

    foreach ([
        'admin.pages.index', 'admin.courses.index', 'admin.daily-content.index', 'admin.leads.index',
        'admin.instructors.index', 'admin.prayer-times.islands', 'admin.prayer-times.groups.index',
        'admin.commerce.index', 'admin.library.index', 'admin.library.reading-alerts', 'admin.bookshop.index', 'admin.pronunciation.index',
        'admin.operations.index', 'admin.operations.features', 'admin.translations.index',
        'admin.users.index', 'admin.settings.index',
    ] as $route) {
        permissionAs($admin)->get(route($route))->assertForbidden();
    }
    // The CSVs behind them too.
    foreach (['admin.pages.export', 'admin.instructors.export', 'admin.prayer-times.groups.export', 'admin.commerce.gift-card-orders.export', 'admin.bookshop.vendors.export', 'admin.translations.export'] as $route) {
        permissionAs($admin)->get(route($route))->assertForbidden();
    }
    // And the Institute's home sends them to their own.
    permissionAs($admin)->get(route('admin.index'))->assertRedirect(route('dashboard'));
});

it('keeps the dean and the supervisor to the school as well', function () {
    // The public website, the instructors on it and prayer times are the
    // system admin's (finding 7 of the admin-panel audit): a headmaster or
    // supervisor could edit the public site by URL. The library office was
    // open to the headmaster; it is the Institute's.
    foreach (['headmaster', 'supervisor'] as $role) {
        $user = seededRoleUser($role);
        foreach (['admin.pages.index', 'admin.instructors.index', 'admin.prayer-times.islands', 'admin.library.index', 'admin.commerce.index', 'admin.bookshop.index'] as $route) {
            permissionAs($user)->get(route($route))->assertForbidden("{$role} on {$route}");
        }
        permissionAs($user)->get(route('people.students.index'))->assertOk();
    }

    // Admissions: the dean sees fees; the supervisor no longer grants places.
    permissionAs(seededRoleUser('headmaster'))->get(route('admin.enrollments.index'))->assertOk();
    permissionAs(seededRoleUser('supervisor'))->get(route('admin.enrollments.index'))->assertForbidden();
});

it('lets the system admin and the Bookstore admin run the Institute', function () {
    $super = seededRoleUser('super_admin');
    foreach (['admin.pages.index', 'admin.instructors.index', 'admin.prayer-times.islands', 'admin.commerce.index', 'admin.library.index', 'admin.bookshop.index', 'admin.pronunciation.index', 'admin.operations.index', 'admin.translations.index', 'admin.users.index', 'admin.settings.index', 'admin.enrollments.payments', 'admin.index'] as $route) {
        permissionAs($super)->get(route($route))->assertOk($route);
    }

    // The Bookstore office stays with its manager, who may not manage the team.
    $manager = seededRoleUser('bookshop_manager');
    permissionAs($manager)->get(route('admin.bookshop.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('team.can_manage', false));
    permissionAs($super)->get(route('admin.bookshop.index'))->assertInertia(fn ($page) => $page->where('team.can_manage', true));
});
