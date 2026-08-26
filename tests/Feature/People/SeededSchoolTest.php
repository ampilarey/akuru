<?php

use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Identity\Models\User;
use App\Domains\People\Models\Student;
use App\Domains\People\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds a usable school including students, years, and a teachers row for the teacher role', function () {
    $this->seed();

    expect(Student::query()->count())->toBeGreaterThan(0)
        ->and(AcademicYear::query()->count())->toBeGreaterThan(0)
        ->and(Teacher::query()->count())->toBeGreaterThan(0);

    $teacherUser = User::query()->where('email', 'teacher@akuru.edu.mv')->first();
    expect($teacherUser)->not->toBeNull()
        ->and($teacherUser->hasRole('teacher'))->toBeTrue()
        ->and(Teacher::query()->where('user_id', $teacherUser->id)->exists())->toBeTrue();
});
