<?php

use App\Domains\Identity\Models\User;
use App\Support\Navigation\ResolveWorkspacesAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * E7 — the dual-identity landing defect.
 *
 * `/dashboard` was a flat elseif chain, so with two identities the *order of
 * the branches* decided where someone landed and the other identity was never
 * offered. A teacher who is also a parent matched `isTeacher()` first.
 *
 * The fix is not a different order — that would only break the same case the
 * other way, landing a teacher on their child's attendance instead of the
 * register they have to fill. Staff still wins the landing; what changed is
 * that the other identity stops being invisible. Since STATUS §5id the rule
 * is the workspace map's order, and the other identity is a workspace the
 * person can switch to from any page.
 */
function makeTeacherParent(): User
{
    Role::findOrCreate('teacher', 'web');
    Role::findOrCreate('parent', 'web');
    Permission::findOrCreate('registers.fill', 'web');

    $teacher = makeTeacherRow();
    $user = User::query()->findOrFail($teacher->user_id);
    $user->assignRole('teacher');
    $user->assignRole('parent');
    $user->givePermissionTo('registers.fill');

    return $user;
}

it('resolves precedence from the map’s order, not branch order or role order', function () {
    $resolve = function (array $roles): array {
        $user = User::factory()->create();
        foreach ($roles as $role) {
            $user->assignRole(Role::findOrCreate($role, 'web'));
        }

        return app(ResolveWorkspacesAction::class)->execute($user);
    };

    expect($resolve(['teacher', 'parent'])['active'])->toBe('school')
        ->and($resolve(['parent', 'teacher'])['active'])->toBe('school')
        ->and($resolve(['admin', 'parent'])['active'])->toBe('school')
        ->and($resolve(['super_admin', 'student'])['active'])->toBe('institute')
        // Family before a shop or a desk: the children come first.
        ->and($resolve(['vendor', 'parent'])['active'])->toBe('family')
        ->and($resolve(['writer', 'student'])['active'])->toBe('learn');

    // Staff always outranks family: a family landing means no staff role at all.
    foreach (['super_admin', 'admin', 'headmaster', 'supervisor', 'teacher'] as $staffRole) {
        expect($resolve([$staffRole, 'parent', 'student'])['active'])->not->toBeIn(['family', 'learn']);
    }
});

it('still lands a teacher-parent on the staff side, not their childs view', function () {
    $user = makeTeacherParent();

    // The guard is the *precedence*, not the destination: teaching is the job
    // they signed in to do. E1b only changed where that landing points — from
    // the register list to the teacher's own home, whose first tile is the
    // register list.
    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('portal.teacher'));
});

it('shows a teacher-parent the family workspace on every inertia page, and a lone teacher nothing extra', function () {
    $user = makeTeacherParent();

    // This is the actual defect: the landing is fine, but before the fix
    // nothing on it said the family view existed.
    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('academics.registers.today'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.workspace', 'school')
            ->has('auth.workspaces', 2)
            ->where('auth.workspaces.1.key', 'family')
            ->where('auth.workspaces.1.label', 'Family')
            ->where('auth.workspaces.1.href', '/portal/home'));

    Permission::findOrCreate('registers.fill', 'web');
    $teacher = makeTeacherRow();
    $lone = User::query()->findOrFail($teacher->user_id);
    $lone->assignRole('teacher');
    $lone->givePermissionTo('registers.fill');

    $this->withoutLocalizationMiddleware()
        ->actingAs($lone)
        ->get(route('academics.registers.today'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('auth.workspaces', 1)->where('auth.workspaces.0.key', 'school'));
});

it('lets a teacher-parent actually switch to the family view', function () {
    $user = makeTeacherParent();

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('workspace.switch', 'family'))
        ->assertRedirect(route('portal.home'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('portal.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/Home')->where('auth.workspace', 'family'));
});
