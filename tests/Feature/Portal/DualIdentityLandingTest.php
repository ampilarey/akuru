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

it('lists every identity a person holds as a view, and none for a person with one', function () {
    // The owner, 2026-09-27: "a parent may be enrolled in a course, and he may
    // be a vendor and a writer." The landing still picks one home; the others
    // are offered from every page (STATUS §5ic).
    $action = app(ResolveDashboardLandingAction::class);

    expect(array_column($action->execute(['teacher', 'parent'])['views'], 'key'))->toBe(['teacher', 'family'])
        ->and(array_column($action->execute(['parent', 'student', 'vendor', 'writer'])['views'], 'route'))->toBe(['portal.home', 'learn.dashboard', 'vendor.index', 'write.index'])
        ->and(array_column($action->execute(['super_admin', 'reviewer', 'course_creator'])['views'], 'key'))->toBe(['admin', 'review', 'catalog'])
        ->and($action->execute(['super_admin'])['views'])->toHaveCount(1)
        ->and($action->execute([])['views'])->toBe([]);

    // A vendor, a writer, a reviewer or a course creator alone lands on their
    // job, not on the public course dashboard.
    expect($action->execute(['vendor'])['kind'])->toBe('vendor')
        ->and($action->execute(['writer'])['kind'])->toBe('writer')
        ->and($action->execute(['reviewer'])['kind'])->toBe('reviewer')
        ->and($action->execute(['course_creator'])['kind'])->toBe('catalog')
        // A parent who is also a vendor: the family first, the shop a pill away.
        ->and($action->execute(['vendor', 'parent'])['kind'])->toBe('portal_home');
});

it('shares the views with every Inertia page and lists them in the Blade menus, translated', function () {
    foreach (['vendor', 'writer'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $user = makeTeacherParent();
    $user->assignRole('vendor');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('academics.registers.today'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('auth.views', 3)
            ->where('auth.views.0', ['key' => 'teacher', 'label' => 'My day', 'href' => '/portal/teacher'])
            ->where('auth.views.1.label', 'Family')
            ->where('auth.views.2', ['key' => 'vendor', 'label' => 'My shop', 'href' => '/vendor']));

    app()->setLocale('dv');
    expect(trans('nav.view_vendor'))->toBe('އަހަރެންގެ ފިހާރަ');
    app()->setLocale('en');

    // A person with one identity is offered nothing extra.
    Role::findOrCreate('teacher', 'web');
    Permission::findOrCreate('registers.fill', 'web');
    $teacher = User::query()->findOrFail(makeTeacherRow()->user_id);
    $teacher->assignRole('teacher');
    $teacher->givePermissionTo('registers.fill');
    $this->withoutLocalizationMiddleware()->actingAs($teacher)->get(route('academics.registers.today'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('auth.views', 1));

    // The Blade shell: the user menu and the phone menu list the same views.
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->assignRole('vendor');
    $html = $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.enrollments.index'))->assertOk()->getContent();
    expect(substr_count($html, 'data-testid="view-vendor"'))->toBe(2)
        ->and(substr_count($html, 'data-testid="view-admin"'))->toBe(2)
        ->and($html)->toContain('Your views');
    $alone = $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create()->assignRole('admin'))->get(route('admin.enrollments.index'))->assertOk()->getContent();
    expect($alone)->not->toContain('Your views');
});
