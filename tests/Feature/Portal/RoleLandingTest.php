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
