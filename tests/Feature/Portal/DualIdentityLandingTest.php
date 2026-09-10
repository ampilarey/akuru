<?php

use App\Domains\Identity\Models\User;
use App\Domains\Portal\Actions\ResolveDashboardLandingAction;
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
 * that the other identity stops being invisible.
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

it('resolves precedence from a stated rule, not branch order', function () {
    $action = app(ResolveDashboardLandingAction::class);

    expect($action->execute(['super_admin'])['kind'])->toBe('super_admin')
        ->and($action->execute(['admin'])['kind'])->toBe('overview')
        ->and($action->execute(['headmaster'])['kind'])->toBe('overview')
        ->and($action->execute(['supervisor'])['kind'])->toBe('supervisor')
        ->and($action->execute(['teacher'])['kind'])->toBe('registers')
        ->and($action->execute(['student'])['kind'])->toBe('portal_home')
        ->and($action->execute(['parent'])['kind'])->toBe('portal_home')
        ->and($action->execute([])['kind'])->toBe('public');
});

it('offers no alternate to anyone holding a single identity', function () {
    $action = app(ResolveDashboardLandingAction::class);

    foreach ([['teacher'], ['parent'], ['student'], ['admin'], ['super_admin'], []] as $roles) {
        expect($action->execute($roles)['alternate'])->toBeNull();
    }
});

it('offers the family view to a teacher who is also a parent', function () {
    $landing = app(ResolveDashboardLandingAction::class)->execute(['teacher', 'parent']);

    // Staff still wins the landing: the register is the job they signed in for.
    expect($landing['kind'])->toBe('registers')
        ->and($landing['alternate'])->toBe(['label' => 'Family view', 'route' => 'portal.home']);
});

it('ignores the order roles happen to be listed in', function () {
    $action = app(ResolveDashboardLandingAction::class);

    // Branch order was the original bug; role order must not resurrect it.
    expect($action->execute(['parent', 'teacher']))
        ->toBe($action->execute(['teacher', 'parent']))
        ->and($action->execute(['parent', 'admin']))
        ->toBe($action->execute(['admin', 'parent']));
});

it('offers the family view from every staff landing', function () {
    $action = app(ResolveDashboardLandingAction::class);

    foreach (['super_admin', 'admin', 'headmaster', 'supervisor', 'teacher'] as $staffRole) {
        expect($action->execute([$staffRole, 'parent'])['alternate'])
            ->toBe(['label' => 'Family view', 'route' => 'portal.home']);
    }
});

it('has no reverse case, because staff always outranks family', function () {
    // A portal_home landing means the person holds no staff role at all, so a
    // "staff view" alternate could never fire. Asserted rather than assumed:
    // this is why the code for one was deleted.
    $action = app(ResolveDashboardLandingAction::class);

    foreach (['super_admin', 'admin', 'headmaster', 'supervisor', 'teacher'] as $staffRole) {
        expect($action->execute([$staffRole, 'parent', 'student'])['kind'])
            ->not->toBe('portal_home');
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

it('shows a teacher-parent a link to the family view on every inertia page', function () {
    $user = makeTeacherParent();

    // This is the actual defect: the landing is fine, but before the fix
    // nothing on it said the family view existed.
    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('academics.registers.today'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.alternate.label', 'Family view')
            ->where('auth.alternate.href', '/portal/home')
        );
});

it('shares no alternate for a teacher who is not also a parent', function () {
    Role::findOrCreate('teacher', 'web');
    Permission::findOrCreate('registers.fill', 'web');

    $teacher = makeTeacherRow();
    $user = User::query()->findOrFail($teacher->user_id);
    $user->assignRole('teacher');
    $user->givePermissionTo('registers.fill');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('academics.registers.today'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.alternate', null));
});

it('lets a teacher-parent actually open the family view', function () {
    $user = makeTeacherParent();

    // The link has to lead somewhere that works, not just render.
    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get('/portal/home')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Home')
            ->where('title', 'Parent Dashboard')
        );
});
