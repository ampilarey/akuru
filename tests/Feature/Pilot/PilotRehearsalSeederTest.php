<?php

use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\Timetable;
use App\Domains\Finance\Models\FeeStructure;
use App\Domains\People\Models\Student;
use App\Domains\People\Models\Teacher;
use Database\Seeders\ClassSeeder;
use Database\Seeders\PilotRehearsalSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\SubjectSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('seeds a messy single-class pilot rehearsal scenario', function () {
    $this->seed([
        RoleSeeder::class,
        SchoolSeeder::class,
        SubjectSeeder::class,
        ClassSeeder::class,
        UserSeeder::class,
        PilotRehearsalSeeder::class,
    ]);

    $year = AcademicYear::query()->where('name', PilotRehearsalSeeder::YEAR_NAME)->sole();
    $class = ClassRoom::query()
        ->where('academic_year_id', $year->id)
        ->where('name', PilotRehearsalSeeder::CLASS_NAME)
        ->where('section', PilotRehearsalSeeder::CLASS_SECTION)
        ->sole();

    $students = Student::query()->where('student_id', 'like', 'PIL-%')->orderBy('student_id')->get();

    expect($students)->toHaveCount(15)
        ->and($students->where('national_id', 'A999001')->count())->toBe(2)
        ->and($students->firstWhere('student_id', 'PIL-03')?->national_id)->toBeNull()
        ->and($students->firstWhere('student_id', 'PIL-04')?->national_id)->toBe('N/A')
        ->and($students->firstWhere('student_id', 'PIL-05')?->national_id)->toBe('0')
        ->and($students->firstWhere('student_id', 'PIL-06')?->national_id)->toBe('-')
        ->and($students->where('first_name', 'Mariyam')->where('last_name', 'Ali')->count())->toBe(2)
        ->and($students->filter(fn (Student $row) => $row->first_name === 'Ahmed'
            && $row->last_name === 'Naseem'
            && $row->date_of_birth?->toDateString() === '2009-04-04')->count())->toBe(2)
        ->and(Teacher::query()->whereIn('email', [
            'teacher@akuru.edu.mv',
            'teacher.quran@akuru.edu.mv',
            'teacher.islam@akuru.edu.mv',
        ])->count())->toBe(3)
        ->and($class->class_teacher_id)->not->toBeNull()
        ->and(DB::table('class_student')->where('class_id', $class->id)->count())->toBe(15)
        ->and(Timetable::query()->where('class_id', $class->id)->count())->toBe(15)
        ->and(FeeStructure::query()->where('name', 'Pilot Grade 5 fees')->where('status', 'active')->exists())->toBeTrue()
        ->and($students->firstWhere('student_id', 'PIL-01')?->guardians()->count())->toBeGreaterThan(0)
        ->and($students->firstWhere('student_id', 'PIL-02')?->guardians()->count())->toBe(0);
});
