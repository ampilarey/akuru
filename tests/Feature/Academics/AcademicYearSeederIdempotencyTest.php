<?php

use App\Domains\Academics\Models\AcademicYear;
use Database\Seeders\ClassSeeder;
use Database\Seeders\PilotRehearsalSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\SubjectSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not insert duplicate academic year names when seeders that create years run twice', function () {
    $this->seed([
        RoleSeeder::class,
        SchoolSeeder::class,
        SubjectSeeder::class,
        ClassSeeder::class,
        UserSeeder::class,
        PilotRehearsalSeeder::class,
    ]);

    $this->seed(PilotRehearsalSeeder::class);

    AcademicYear::query()->firstOrCreate(
        ['name' => '2024-2025'],
        [
            'start_date' => '2024-09-01',
            'end_date' => '2025-06-30',
            'is_current' => false,
            'description' => 'Academic Year 2024-2025',
        ],
    );
    AcademicYear::query()->firstOrCreate(
        ['name' => '2024-2025'],
        [
            'start_date' => '2024-09-01',
            'end_date' => '2025-06-30',
        ],
    );

    AcademicYear::query()->firstOrCreate(
        ['name' => '2026-2027 Extra'],
        [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ],
    );
    AcademicYear::query()->firstOrCreate(
        ['name' => '2026-2027 Extra'],
        [
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ],
    );

    $duplicates = AcademicYear::query()
        ->select('name')
        ->groupBy('name')
        ->havingRaw('count(*) > 1')
        ->pluck('name');

    expect($duplicates)->toBeEmpty()
        ->and(AcademicYear::query()->where('name', PilotRehearsalSeeder::YEAR_NAME)->count())->toBe(1)
        ->and(AcademicYear::query()->where('name', '2024-2025')->count())->toBe(1)
        ->and(AcademicYear::query()->where('name', '2026-2027 Extra')->count())->toBe(1);
});
