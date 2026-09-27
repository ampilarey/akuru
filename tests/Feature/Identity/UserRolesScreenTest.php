<?php

use App\Domains\Identity\Actions\SetUserActiveAction;
use App\Domains\Identity\Actions\SetUserRolesAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The role and access screen under Manage users (ADR-040 slice 4, BACKLOG
 * C8, docs/ADMIN_PANEL.md finding 5). No screen assigned or removed a role
 * or reactivated a deactivated account: roles came from seeders,
 * `bookshop:grant-manager` or tinker. The system admin now does both from
 * the panel, with the protections `DeleteUserAccountAction` has — never
 * themselves, never the last System admin.
 */
function systemAdmin(string $name = 'The System Admin'): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->assignRole(Role::findOrCreate('super_admin', 'web'));

    return $user->fresh();
}

function rolesAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

it('opens from the users list and shows the roles by label, with the protected ones locked', function () {
    $super = systemAdmin();
    $teacher = User::factory()->create(['name' => 'Ustadh Ali']);
    $teacher->assignRole(Role::findOrCreate('teacher', 'web'));

    // The list (Inertia since C9 slice 2) carries the teacher's row, newest first,
    // and the page links every row to its Roles & access screen by id.
    rolesAs($super)->get(route('admin.users.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Identity/Users')->where('users.0.id', $teacher->id)->where('users.0.role_label', 'Teacher'));

    rolesAs($super)->get(route('admin.users.roles', $teacher))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Identity/UserRoles')
            ->where('user.name', 'Ustadh Ali')->where('user.roles', ['teacher'])->where('user.labels', 'Teacher')->where('user.is_active', true)->where('user.is_self', false)
            ->where('roles.0', ['key' => 'super_admin', 'label' => 'System admin'])
            ->where('roles.2', ['key' => 'headmaster', 'label' => 'Dean'])
            ->has('roles', 12)
            ->where('locked', [])
            ->where('t.roles_title', 'Roles & access'));

    // The only System admin, looking at themselves: the role is locked.
    rolesAs($super)->get(route('admin.users.roles', $super))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('user.is_self', true)->where('locked', ['super_admin']));

    // A second System admin looking at the first: still the last-but-one rule does not bite, so nothing is locked.
    $other = systemAdmin('Another');
    rolesAs($other)->get(route('admin.users.roles', $super))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locked', []));

    // Not the educational admin's screen.
    $admin = User::factory()->create();
    $admin->assignRole(Role::findOrCreate('admin', 'web'));
    rolesAs($admin)->get(route('admin.users.roles', $teacher))->assertForbidden();
});

it('sets the roles a person holds, and gives a new teacher their teachers row', function () {
    $super = systemAdmin();
    makeSchool();
    $person = User::factory()->create(['name' => 'Fathimath']);
    $person->assignRole(Role::findOrCreate('parent', 'web'));

    rolesAs($super)->put(route('admin.users.roles.update', $person), ['roles' => ['parent', 'teacher', 'headmaster']])
        ->assertRedirect()->assertSessionHas('success', 'Fathimath now holds: Dean, Teacher, Parent.');
    expect($person->fresh()->getRoleNames()->sort()->values()->all())->toBe(['headmaster', 'parent', 'teacher'])
        ->and(\App\Domains\People\Models\Teacher::query()->where('user_id', $person->id)->exists())->toBeTrue();

    // Taking a role away.
    rolesAs($super)->put(route('admin.users.roles.update', $person), ['roles' => ['teacher']])->assertRedirect()->assertSessionHas('success');
    expect($person->fresh()->getRoleNames()->all())->toBe(['teacher']);

    // A role the label file does not name is refused at the door.
    rolesAs($super)->put(route('admin.users.roles.update', $person), ['roles' => ['teacher', 'night_watch']])->assertSessionHasErrors('roles.1');
    expect(fn () => app(SetUserRolesAction::class)->execute($person, ['night_watch'], $super->id))->toThrow(ValidationException::class);
    expect(Role::query()->where('name', 'night_watch')->exists())->toBeFalse();
});

it('never removes the actor’s own System admin role, nor the last one', function () {
    $super = systemAdmin();

    rolesAs($super)->put(route('admin.users.roles.update', $super), ['roles' => ['headmaster']])
        ->assertRedirect()->assertSessionHas('error', 'You cannot remove your own System admin role.');
    expect($super->fresh()->hasRole('super_admin'))->toBeTrue();

    // A System admin may add themselves the dean's role (ADR-040: that is how they open the School).
    rolesAs($super)->put(route('admin.users.roles.update', $super), ['roles' => ['super_admin', 'headmaster']])->assertSessionHas('success');
    expect($super->fresh()->hasRole('headmaster'))->toBeTrue();

    // Another System admin cannot strip the last one either.
    $other = systemAdmin('Another');
    // Two now, so removing one is allowed …
    rolesAs($other)->put(route('admin.users.roles.update', $super), ['roles' => ['headmaster']])->assertSessionHas('success');
    expect($super->fresh()->hasRole('super_admin'))->toBeFalse();
    // … and now $other is the last: nobody may remove theirs (a dean cannot
    // even open the screen; the action refuses in its own right).
    rolesAs($super->fresh())->put(route('admin.users.roles.update', $other), ['roles' => []])->assertForbidden();
    expect(fn () => app(SetUserRolesAction::class)->execute($other, [], $super->id))->toThrow(ValidationException::class, 'The last System admin keeps that role.');
    expect($other->fresh()->hasRole('super_admin'))->toBeTrue();
});

it('deactivates and reactivates an account, never the actor’s own nor the last active System admin', function () {
    $super = systemAdmin();
    $person = User::factory()->create(['name' => 'Hassan', 'is_active' => true]);

    rolesAs($super)->post(route('admin.users.active', $person), ['active' => 0])
        ->assertRedirect()->assertSessionHas('success', 'Hassan can no longer sign in. Their history is kept.');
    expect((bool) $person->fresh()->is_active)->toBeFalse();

    // The list says so, and the screen offers the way back.
    rolesAs($super)->get(route('admin.users.index'))->assertInertia(fn (Assert $page) => $page->where('users.0.name', 'Hassan')->where('users.0.is_active', false));
    rolesAs($super)->get(route('admin.users.roles', $person))->assertInertia(fn (Assert $page) => $page->where('user.is_active', false));

    rolesAs($super)->post(route('admin.users.active', $person), ['active' => 1])
        ->assertRedirect()->assertSessionHas('success', 'Hassan can sign in again.');
    expect((bool) $person->fresh()->is_active)->toBeTrue();

    // Never yourself.
    rolesAs($super)->post(route('admin.users.active', $super), ['active' => 0])->assertSessionHas('error', 'You cannot deactivate your own account.');
    expect((bool) $super->fresh()->is_active)->toBeTrue();

    // Never the last active System admin.
    $other = systemAdmin('Another');
    $other->forceFill(['is_active' => false])->save();
    expect(fn () => app(SetUserActiveAction::class)->execute($super, false, $other->id))->toThrow(ValidationException::class, 'The last active System admin stays active.');
    // With the other one active again, the first may be deactivated by them.
    $other->forceFill(['is_active' => true])->save();
    rolesAs($other)->post(route('admin.users.active', $super), ['active' => 0])->assertSessionHas('success');
    expect((bool) $super->fresh()->is_active)->toBeFalse();
});
