<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = \App\Models\School::first();
        
        // Create Admin User
        $admin = \App\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin@akuru.edu.mv',
            'password' => bcrypt('password'),
            'phone' => '+960 123-4567',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '1980-01-01',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $admin->assignRole('admin');
        
        // Create Headmaster User
        $headmaster = \App\Models\User::create([
            'name' => 'Dr. Ahmed Ibrahim',
            'email' => 'headmaster@akuru.edu.mv',
            'password' => bcrypt('password'),
            'phone' => '+960 123-4568',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '1975-05-15',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $headmaster->assignRole('headmaster');
        
        // Create Teacher User
        $teacher = \App\Models\User::create([
            'name' => 'Ustadh Mohamed Ali',
            'email' => 'teacher@akuru.edu.mv',
            'password' => bcrypt('password'),
            'phone' => '+960 123-4569',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '1985-03-20',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $teacher->assignRole('teacher');
        
        // Create Student User
        $student = \App\Models\User::create([
            'name' => 'Ahmed Hassan',
            'email' => 'student@akuru.edu.mv',
            'password' => bcrypt('password'),
            'phone' => '+960 123-4570',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '2010-08-10',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $student->assignRole('student');
        
        // Create Parent User
        $parent = \App\Models\User::create([
            'name' => 'Hassan Ahmed',
            'email' => 'parent@akuru.edu.mv',
            'password' => bcrypt('password'),
            'phone' => '+960 123-4571',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '1980-12-05',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $parent->assignRole('parent');
    }
}
