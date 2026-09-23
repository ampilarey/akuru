<?php

use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\Subject;
use App\Domains\People\Models\Student;
use Database\Seeders\PilotRehearsalSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Staging had a school, a year and an admin@ and nothing else the pilot
 * seeder assumes: it stopped at "No query results for model [Subject]"
 * (STATUS §5fz). It now plants the subjects it needs, and every seeder it
 * calls finds its rows first — so it runs on a thin database, and twice.
 */
it('runs on a database with only roles, a school and the logins, and runs twice', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(SchoolSeeder::class);
    $this->seed(UserSeeder::class);

    expect(Subject::query()->count())->toBe(0);

    $this->seed(PilotRehearsalSeeder::class);
    $subjects = Subject::query()->count();
    $students = Student::query()->count();
    $classes = ClassRoom::query()->count();

    $this->seed(PilotRehearsalSeeder::class);

    expect(Subject::query()->where('code', 'ARB101')->exists())->toBeTrue()
        ->and($students)->toBeGreaterThan(0)
        ->and(Subject::query()->count())->toBe($subjects)
        ->and(Student::query()->count())->toBe($students)
        ->and(ClassRoom::query()->count())->toBe($classes);
});
