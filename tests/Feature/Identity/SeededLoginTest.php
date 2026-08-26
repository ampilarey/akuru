<?php

use App\Domains\Identity\Models\UserContact;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets documented seed emails log in via verified user_contacts', function () {
    $this->seed([
        RoleSeeder::class,
        SchoolSeeder::class,
        UserSeeder::class,
    ]);

    expect(UserContact::query()->where('type', 'email')->whereNotNull('verified_at')->whereIn('value', [
        'admin@akuru.edu.mv',
        'teacher@akuru.edu.mv',
        'parent@akuru.edu.mv',
        'student@akuru.edu.mv',
        'headmaster@akuru.edu.mv',
        'supervisor@akuru.edu.mv',
    ])->count())->toBe(6);

    $this->post(route('login'), [
        'identifier' => 'admin@akuru.edu.mv',
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticated();
    $this->post(route('logout'));
    $this->assertGuest();

    $this->post(route('login'), [
        'identifier' => 'teacher@akuru.edu.mv',
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticated();
});
