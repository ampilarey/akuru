<?php

use App\Domains\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Staging had admin@ and not teacher@, and `UserSeeder` — six plain
 * `create` calls — stopped at the first duplicate email with nothing
 * planted (STATUS §5fz). It now finds each login by email, so it fills in
 * whichever of the seven are missing and leaves the rest alone. The seventh,
 * `superadmin@`, holds `super_admin` (ADR-040 slice 2: the educational admin
 * no longer holds the Institute, so the walks need a login that does).
 */
it('plants the seven pilot logins on a database that already has some of them, and runs twice', function () {
    $this->seed(RoleSeeder::class);
    User::factory()->create(['email' => 'admin@akuru.edu.mv', 'name' => 'Already Here']);

    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    $logins = ['superadmin' => 'super_admin', 'admin' => 'admin', 'headmaster' => 'headmaster', 'teacher' => 'teacher', 'student' => 'student', 'parent' => 'parent', 'supervisor' => 'supervisor'];
    foreach ($logins as $email => $role) {
        expect(User::query()->where('email', $email.'@akuru.edu.mv')->count())->toBe(1, $email)
            ->and(User::query()->where('email', $email.'@akuru.edu.mv')->first()->hasRole($role))->toBeTrue($email);
    }
    // The system admin is the only seeded login holding the Institute.
    expect(User::query()->where('email', 'admin@akuru.edu.mv')->first()->hasRole('super_admin'))->toBeFalse();

    // The one that was there is left as it was.
    expect(User::query()->where('email', 'admin@akuru.edu.mv')->value('name'))->toBe('Already Here');
});
