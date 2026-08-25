<?php

use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Enums\RoomType;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\Period;
use App\Domains\Academics\Models\Room;
use App\Domains\Academics\Models\Subject;
use App\Domains\Identity\Models\User;
use App\Domains\People\Models\Teacher;
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

function makeSubject(): Subject
{
    return Subject::query()->create([
        'school_id' => makeSchool()->id,
        'name' => 'Arabic',
        'code' => 'ARB'.fake()->unique()->numerify('###'),
        'type' => 'Arabic',
        'is_active' => true,
    ]);
}

function makeTeacherRow(): Teacher
{
    $user = User::factory()->create();

    return Teacher::query()->create([
        'user_id' => $user->id,
        'school_id' => makeSchool()->id,
        'teacher_id' => 'T'.$user->id,
        'first_name' => 'Fatimat',
        'last_name' => 'Ali',
        'date_of_birth' => '1990-01-01',
        'gender' => 'female',
        'phone' => '7820288',
        'address' => 'Malé',
        'email' => $user->email,
        'qualification' => 'BA',
        'specialization' => 'Arabic',
        'joining_date' => '2020-01-01',
        'status' => 'active',
    ]);
}

function makePeriodRow(string $start = '08:00:00', string $end = '08:45:00', int $order = 1): Period
{
    return Period::query()->create([
        'school_id' => makeSchool()->id,
        'name' => 'P'.$order,
        'start_time' => $start,
        'end_time' => $end,
        'order' => $order,
        'is_break' => false,
        'is_active' => true,
    ]);
}

function makeRoomRow(?string $name = null): Room
{
    return Room::query()->create([
        'name' => $name ?? 'Room '.fake()->unique()->numerify('###'),
        'type' => RoomType::Lab,
        'bookable' => true,
        'active' => true,
    ]);
}

function makeCoursePlan(array $overrides = []): \App\Domains\Academics\Models\CoursePlan
{
    $year = $overrides['year'] ?? makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    unset($overrides['year']);

    $defaults = [
        'academic_year' => $year->name,
        'academic_year_id' => $year->id,
        'title' => 'Term plan',
        'status' => 'active',
    ];
    if (! array_key_exists('teacher_id', $overrides)) {
        $defaults['teacher_id'] = makeTeacherRow()->id;
    }
    if (! array_key_exists('subject_id', $overrides)) {
        $defaults['subject_id'] = makeSubject()->id;
    }
    if (! array_key_exists('classroom_id', $overrides)) {
        $defaults['classroom_id'] = makeClass($year)->id;
    }

    return \App\Domains\Academics\Models\CoursePlan::query()->create(array_merge($defaults, $overrides));
}

function makeLessonLog(array $overrides = []): \App\Domains\Academics\Models\LessonLog
{
    $year = $overrides['year'] ?? makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    unset($overrides['year']);

    $defaults = [
        'academic_year_id' => $year->id,
        'date' => '2026-08-24',
        'status' => 'expected',
        'taught_summary' => null,
    ];
    if (! array_key_exists('teacher_id', $overrides)) {
        $defaults['teacher_id'] = makeTeacherRow()->id;
    }
    if (! array_key_exists('subject_id', $overrides)) {
        $defaults['subject_id'] = makeSubject()->id;
    }
    if (! array_key_exists('classroom_id', $overrides)) {
        $defaults['classroom_id'] = makeClass($year)->id;
    }

    return \App\Domains\Academics\Models\LessonLog::query()->create(array_merge($defaults, $overrides));
}
