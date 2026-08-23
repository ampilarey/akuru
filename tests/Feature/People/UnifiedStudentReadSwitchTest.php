<?php

use App\Domains\Admissions\Services\Enrollment\EnrollmentService;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\People\Models\RegistrationStudent;
use App\Domains\People\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function verifiedAdult(): User
{
    $user = User::factory()->create(['name' => 'Adult User']);
    UserContact::query()->create([
        'user_id' => $user->id,
        'type' => 'mobile',
        'value' => '7820288',
        'is_primary' => true,
        'verified_at' => now(),
    ]);

    return $user;
}

it('dual-writes a Student and enrollment reads use the unified model', function () {
    $user = verifiedAdult();
    $course = Course::factory()->create([
        'registration_fee_amount' => 0,
        'requires_admin_approval' => false,
    ]);

    $result = app(EnrollmentService::class)->enrollAdultSelf($user, [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'dob' => now()->subYears(20)->toDateString(),
        'gender' => 'male',
    ], [$course->id]);

    $enrollment = $result->createdEnrollments[0] ?? CourseEnrollment::query()->first();
    $rs = RegistrationStudent::query()->where('user_id', $user->id)->sole();
    $student = Student::query()->where('legacy_registration_student_id', $rs->id)->sole();

    expect($enrollment)->not->toBeNull()
        ->and($enrollment->unified_student_id)->toBe($student->id)
        ->and($enrollment->student)->toBeInstanceOf(Student::class)
        ->and($enrollment->student->full_name)->toBe('John Doe')
        ->and($enrollment->student->dob?->toDateString())->toBe($student->date_of_birth->toDateString())
        ->and($enrollment->legacyStudent->id)->toBe($rs->id)
        ->and($user->fresh()->student->id)->toBe($student->id);
});

it('dual-writes guardian_student when a parent enrolls a child', function () {
    $parent = verifiedAdult();
    $course = Course::factory()->create([
        'registration_fee_amount' => 0,
        'requires_admin_approval' => false,
    ]);

    app(EnrollmentService::class)->enrollByParent($parent, [
        'first_name' => 'Noor',
        'last_name' => 'Ahmed',
        'dob' => now()->subYears(10)->toDateString(),
        'gender' => 'female',
        'relationship' => 'mother',
    ], [$course->id], null, ['relationship' => 'mother']);

    $rs = RegistrationStudent::query()->where('first_name', 'Noor')->sole();
    $student = Student::query()->where('legacy_registration_student_id', $rs->id)->sole();

    expect($parent->courseStudents()->pluck('students.id')->all())->toContain($student->id)
        ->and($student->guardians)->toHaveCount(1)
        ->and($student->guardians->first()->name)->toBe($parent->name)
        ->and($student->guardians->first()->pivot->relationship)->toBe('mother')
        ->and($student->guardians->first()->pivot->is_primary)->toBeTrue();
});

it('marks RegistrationStudent as deprecated', function () {
    $ref = new ReflectionClass(RegistrationStudent::class);

    expect($ref->getDocComment())->toContain('@deprecated');
});
