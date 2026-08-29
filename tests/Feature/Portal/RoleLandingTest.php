<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('sends a teacher from the dashboard to today registers', function () {
    Role::findOrCreate('teacher', 'web');
    Permission::findOrCreate('registers.fill', 'web');

    $teacher = makeTeacherRow();
    $user = User::query()->findOrFail($teacher->user_id);
    $user->assignRole('teacher');
    $user->givePermissionTo('registers.fill');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('academics.registers.today'));

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('academics.registers.today'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Academics/Registers/Today'));
});

it('sends an admin from the dashboard to the composed staff overview', function () {
    $user = actingPeopleAdmin(['registers.manage', 'exams.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('portal.overview'));

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

// The three branches above redirect; the three below still render a view
// from the dashboard itself. They are the branches that could silently be
// pointed at a deleted view, so pin the view each one renders.

it('renders the super admin dashboard in place', function () {
    Role::findOrCreate('super_admin', 'web');

    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewIs('dashboard.super-admin');
});

it('renders the supervisor dashboard in place', function () {
    Role::findOrCreate('supervisor', 'web');

    $user = User::factory()->create();
    $user->assignRole('supervisor');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewIs('dashboard.supervisor');
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
