<?php

use App\Domains\Admissions\Services\Enrollment\EnrollmentService;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\People\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

it('writes the Student alone, and enrollment reads use it (Deploy 3 slice 2)', function () {
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
    $student = Student::query()->where('user_id', $user->id)->sole();

    expect($enrollment)->not->toBeNull()
        ->and($enrollment->unified_student_id)->toBe($student->id)
        ->and($enrollment->student)->toBeInstanceOf(Student::class)
        ->and($enrollment->student->full_name)->toBe('John Doe')
        ->and($enrollment->student->dob?->toDateString())->toBe($student->date_of_birth->toDateString())
        ->and(DB::table('archived_registration_students')->count())->toBe(0)
        ->and($user->fresh()->student->id)->toBe($student->id);
});

it('writes guardian_student alone when a parent enrolls a child (Deploy 3 slice 2)', function () {
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

    $student = Student::query()->where('first_name', 'Noor')->sole();

    expect(DB::table('archived_registration_students')->count())->toBe(0)
        ->and(DB::table('archived_student_guardians')->count())->toBe(0)
        ->and($student->user_id)->toBeNull()
        ->and($parent->courseStudents()->pluck('students.id')->all())->toContain($student->id)
        ->and($student->guardians)->toHaveCount(1)
        ->and($student->guardians->first()->name)->toBe($parent->name)
        ->and($student->guardians->first()->pivot->relationship)->toBe('mother')
        ->and((bool) $student->guardians->first()->pivot->is_primary)->toBeTrue();
});

it('has retired the legacy model with its table (Deploy 3 slice 3)', function () {
    expect(class_exists('App\\Domains\\People\\Models\\RegistrationStudent'))->toBeFalse()
        ->and(Schema::hasTable('registration_students'))->toBeFalse()
        ->and(Schema::hasTable('archived_registration_students'))->toBeTrue()
        ->and(Schema::hasColumn('students', 'legacy_registration_student_id'))->toBeFalse();
});
