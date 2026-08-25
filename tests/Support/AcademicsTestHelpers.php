<?php

use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Settings\Models\School;

function makeYear(array $overrides = []): AcademicYear
{
    return AcademicYear::query()->create(array_merge([
        'name' => '2026-2027',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_current' => false,
        'status' => AcademicYearStatus::Upcoming,
    ], $overrides));
}

function makeSchool(): School
{
    return School::query()->first() ?? School::query()->create([
        'name' => 'Test School',
        'address' => 'Malé',
        'phone' => '7972434',
        'email' => 'school@example.com',
        'principal_name' => 'Principal',
        'established_year' => '2010',
        'is_active' => true,
    ]);
}

function makeClass(AcademicYear $year, string $name = 'Grade 1', string $section = 'A'): ClassRoom
{
    return ClassRoom::query()->create([
        'school_id' => makeSchool()->id,
        'academic_year_id' => $year->id,
        'name' => $name,
        'section' => $section,
        'level' => 'Primary',
        'capacity' => 20,
        'is_active' => true,
    ]);
}
