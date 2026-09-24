<?php

use App\Domains\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * `/academics/attendance` is the whole school's attendance: every pupil's
 * rows, the chronic-absence list, the unexcused list, and a CSV of each. Until
 * 2026-09-24 it admitted `view_attendance`, which the `parent` and `student`
 * roles hold for their own portal rows — so any family could type the URL
 * and read the school. Found by the navigation slice's open-every-link check
 * (STATUS §5ga); fixed by gating on what only staff hold.
 */
function roleUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('refuses a parent and a student the school-wide attendance report and its CSV', function (string $role) {
    $this->seed(RoleSeeder::class);
    makeYear();
    $user = roleUser($role);

    expect($user->can('view_attendance'))->toBeTrue(); // what used to admit them

    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->get(route('academics.attendance.index'))
        ->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->get(route('academics.attendance.export', ['kind' => 'unexcused']))
        ->assertForbidden();
})->with(['parent', 'student']);

it('still opens for every staff role', function (string $role) {
    $this->seed(RoleSeeder::class);
    makeYear();

    $this->withoutLocalizationMiddleware()->actingAs(roleUser($role))
        ->get(route('academics.attendance.index'))
        ->assertOk();
})->with(['super_admin', 'admin', 'headmaster', 'supervisor', 'teacher']);
