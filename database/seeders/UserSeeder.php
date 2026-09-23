<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * The six pilot logins, by email. `firstOrCreate` so this can run on a
     * database that already has some of them — staging had admin@ and not
     * teacher@, and a plain `create` stopped at the first duplicate email
     * with nothing planted (STATUS §5fz). A user that exists is left as is,
     * password included; only the role and the verified contact are ensured.
     */
    public function run(): void
    {
        $school = \App\Domains\Settings\Models\School::query()->first();
        if ($school === null) {
            $this->call(SchoolSeeder::class);
            $school = \App\Domains\Settings\Models\School::query()->first();
        }

        // Create Admin User
        $admin = \App\Domains\Identity\Models\User::firstOrCreate(['email' => 'admin@akuru.edu.mv'], [
            'name' => 'Admin User',
            'password' => bcrypt('password'),
            'phone' => '+960 782 0288',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '1980-01-01',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        // Create Headmaster User
        $headmaster = \App\Domains\Identity\Models\User::firstOrCreate(['email' => 'headmaster@akuru.edu.mv'], [
            'name' => 'Dr. Ahmed Ibrahim',
            'password' => bcrypt('password'),
            'phone' => '+960 797 2434',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '1975-05-15',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $headmaster->assignRole('headmaster');

        // Create Teacher User
        $teacher = \App\Domains\Identity\Models\User::firstOrCreate(['email' => 'teacher@akuru.edu.mv'], [
            'name' => 'Ustadh Mohamed Ali',
            'password' => bcrypt('password'),
            'phone' => '+960 782 0288',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '1985-03-20',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $teacher->assignRole('teacher');
        app(\App\Domains\People\Actions\EnsureTeacherRowAction::class)->execute($teacher->id, (int) $school->id);

        // Create Student User
        $student = \App\Domains\Identity\Models\User::firstOrCreate(['email' => 'student@akuru.edu.mv'], [
            'name' => 'Ahmed Hassan',
            'password' => bcrypt('password'),
            'phone' => '+960 797 2434',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '2010-08-10',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $student->assignRole('student');

        // Create Parent User
        $parent = \App\Domains\Identity\Models\User::firstOrCreate(['email' => 'parent@akuru.edu.mv'], [
            'name' => 'Hassan Ahmed',
            'password' => bcrypt('password'),
            'phone' => '+960 782 0288',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '1980-12-05',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $parent->assignRole('parent');

        // Create Supervisor User
        $supervisor = \App\Domains\Identity\Models\User::firstOrCreate(['email' => 'supervisor@akuru.edu.mv'], [
            'name' => 'Supervisor Ibrahim',
            'password' => bcrypt('password'),
            'phone' => '+960 797 2434',
            'address' => 'Malé, Maldives',
            'date_of_birth' => '1982-06-15',
            'gender' => 'male',
            'is_active' => true,
        ]);
        $supervisor->assignRole('supervisor');

        $ensure = app(\App\Domains\Identity\Actions\EnsureVerifiedEmailContactAction::class);
        foreach ([$admin, $headmaster, $teacher, $student, $parent, $supervisor] as $user) {
            $ensure->execute($user);
        }
    }
}
