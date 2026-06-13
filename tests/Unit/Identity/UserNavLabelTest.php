<?php

namespace Tests\Unit\Identity;

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserNavLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_nav_label_uses_email_when_name_matches_role_title(): void
    {
        Role::findOrCreate('super_admin', 'web');

        $user = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@akuru.edu.mv',
        ]);
        $user->assignRole('super_admin');

        $this->assertTrue($user->nameDuplicatesPrimaryRole());
        $this->assertSame('admin@akuru.edu.mv', $user->navLabel());
    }

    public function test_nav_label_uses_name_when_distinct_from_role(): void
    {
        Role::findOrCreate('teacher', 'web');

        $user = User::factory()->create([
            'name' => 'Ahmed Hassan',
            'email' => 'teacher@akuru.edu.mv',
        ]);
        $user->assignRole('teacher');

        $this->assertFalse($user->nameDuplicatesPrimaryRole());
        $this->assertSame('Ahmed Hassan', $user->navLabel());
    }
}
