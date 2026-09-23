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
 * whichever of the six are missing and leaves the rest alone.
 */
it('plants the six pilot logins on a database that already has some of them, and runs twice', function () {
    $this->seed(RoleSeeder::class);
    User::factory()->create(['email' => 'admin@akuru.edu.mv', 'name' => 'Already Here']);

    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    $emails = ['admin', 'headmaster', 'teacher', 'student', 'parent', 'supervisor'];
    foreach ($emails as $role) {
        expect(User::query()->where('email', $role.'@akuru.edu.mv')->count())->toBe(1, $role)
            ->and(User::query()->where('email', $role.'@akuru.edu.mv')->first()->hasRole($role))->toBeTrue($role);
    }

    // The one that was there is left as it was.
    expect(User::query()->where('email', 'admin@akuru.edu.mv')->value('name'))->toBe('Already Here');
});
