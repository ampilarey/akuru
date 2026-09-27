<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('sends a teacher from the dashboard to their own home', function () {
    Role::findOrCreate('teacher', 'web');
    Permission::findOrCreate('registers.fill', 'web');

    $teacher = makeTeacherRow();
    $user = User::query()->findOrFail($teacher->user_id);
    $user->assignRole('teacher');
    $user->givePermissionTo('registers.fill');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('dashboard'))
        // E1b: the register list is a task queue, not a home. It is still the
        // first tile on the page they now land on.
        ->assertRedirect(route('portal.teacher'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('portal.teacher'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/TeacherHome'));

    // The queue itself still works and is still reachable.
    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('academics.registers.today'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Academics/Registers/Today'));
});

it('sends an admin from the dashboard to the School office, which links the composed staff overview', function () {
    // STATUS §5id: the educational admin's workspace is the School, and its
    // home is the School office with today's numbers on top.
    $user = actingPeopleAdmin(['registers.manage', 'exams.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('school.index'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('portal.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/StaffOverview')
            ->where('title', 'Staff overview')
        );
});

it('sends a parent from the dashboard to the composed portal home', function () {
    Role::findOrCreate('parent', 'web');

    $user = User::factory()->create();
    $user->assignRole('parent');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('portal.home'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('portal.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Home')
            ->where('title', 'Parent Dashboard')
        )
        ->assertSee('Attendance', false)
        ->assertSee('Absence notes', false)
        ->assertDontSee('Admin Dashboard', false);
});

it('sends a student from the dashboard to the composed portal home', function () {
    Role::findOrCreate('student', 'web');

    $user = User::factory()->create();
    $user->assignRole('student');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('portal.home'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('portal.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Home')
            ->where('title', 'Student Dashboard')
        );
});

// The super admin and the supervisor land on the admin panel too (STATUS
// §5ia); their full dashboards keep their own addresses, gated to the role,
// and are the views that could silently be pointed at a deleted file.

it('sends the super admin to the Institute home and keeps the full dashboard at its own address', function () {
    Role::findOrCreate('super_admin', 'web');
    Role::findOrCreate('supervisor', 'web');

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('dashboard'))->assertRedirect(route('admin.index'));
    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('dashboard.numbers'))->assertOk()->assertViewIs('dashboard.super-admin');
    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('dashboard.supervisor'))->assertForbidden();
});

it('sends the supervisor to the School office and keeps the full dashboard at its own address', function () {
    Role::findOrCreate('supervisor', 'web');
    Role::findOrCreate('super_admin', 'web');

    $user = User::factory()->create();
    $user->assignRole('supervisor');

    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('dashboard'))->assertRedirect(route('school.index'));
    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('dashboard.supervisor'))->assertOk()->assertViewIs('dashboard.supervisor');
    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('dashboard.numbers'))->assertForbidden();
});

it('falls through to the public-user dashboard when the account has no role', function () {
    $user = User::factory()->create();

    expect($user->getRoleNames())->toBeEmpty();

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewIs('dashboard.public-user');
});

it('sends a vendor to their shop and a writer to their desk, not to the public course dashboard', function () {
    foreach (['vendor', 'writer'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $vendor = User::factory()->create();
    $vendor->assignRole('vendor');
    $this->withoutLocalizationMiddleware()->actingAs($vendor)->get(route('dashboard'))->assertRedirect(route('vendor.index'));

    $writer = User::factory()->create();
    $writer->assignRole('writer');
    $this->withoutLocalizationMiddleware()->actingAs($writer)->get(route('dashboard'))->assertRedirect(route('write.index'));
});
