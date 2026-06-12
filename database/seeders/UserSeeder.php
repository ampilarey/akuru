<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = \App\Domains\Settings\Models\School::first();

        // Create Admin User
        $admin = \App\Domains\Identity\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin@akuru.edu.mv',
            'password' => bcrypt('password'),
            'phone' => '+960 782 0288',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '1980-01-01',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        // Create Headmaster User
        $headmaster = \App\Domains\Identity\Models\User::create([
            'name' => 'Dr. Ahmed Ibrahim',
            'email' => 'headmaster@akuru.edu.mv',
            'password' => bcrypt('password'),
            'phone' => '+960 797 2434',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '1975-05-15',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $headmaster->assignRole('headmaster');

        // Create Teacher User
        $teacher = \App\Domains\Identity\Models\User::create([
            'name' => 'Ustadh Mohamed Ali',
            'email' => 'teacher@akuru.edu.mv',
            'password' => bcrypt('password'),
            'phone' => '+960 782 0288',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '1985-03-20',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $teacher->assignRole('teacher');

        // Create Student User
        $student = \App\Domains\Identity\Models\User::create([
            'name' => 'Ahmed Hassan',
            'email' => 'student@akuru.edu.mv',
            'password' => bcrypt('password'),
            'phone' => '+960 797 2434',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '2010-08-10',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $student->assignRole('student');

        // Create Parent User
        $parent = \App\Domains\Identity\Models\User::create([
            'name' => 'Hassan Ahmed',
            'email' => 'parent@akuru.edu.mv',
            'password' => bcrypt('password'),
            'phone' => '+960 782 0288',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '1980-12-05',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $parent->assignRole('parent');

        // Create Supervisor User
        $supervisor = \App\Domains\Identity\Models\User::create([
            'name' => 'Supervisor Ibrahim',
            'email' => 'supervisor@akuru.edu.mv',
            'password' => bcrypt('password'),
            'phone' => '+960 797 2434',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '1982-06-15',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $supervisor->assignRole('supervisor');
    }
}
