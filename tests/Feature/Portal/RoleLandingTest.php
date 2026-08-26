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

it('shows a parent-titled landing instead of the admin dashboard', function () {
    Role::findOrCreate('parent', 'web');

    $user = User::factory()->create();
    $user->assignRole('parent');

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Parent Dashboard', false)
        ->assertSee('Attendance', false)
        ->assertSee('Absence notes', false)
        ->assertDontSee('Admin Dashboard', false);
});
