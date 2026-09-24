<?php

use App\Domains\Admissions\Services\Enrollment\EnrollmentService;
use App\Domains\Courses\Actions\EnrollUnifiedStudentInOfferingAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Offerings\Actions\SaveCourseOfferingAction;
use App\Domains\People\Actions\RegisterCourseStudentAction;
use App\Domains\People\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * S1 Deploy 3, slice 2 (OWNER_ACTIONS item 10): registration, offering
 * enrolment and guardian links write `students` and `guardian_student` only.
 * `registration_students` and `student_guardians` stop growing, so slice 3 can
 * archive them.
 */
function registeringParent(): User
{
    $user = User::factory()->create(['name' => 'Mariyam Rasheed']);
    UserContact::query()->create([
        'user_id' => $user->id,
        'type' => 'mobile',
        'value' => '7'.random_int(100000, 999999),
        'is_primary' => true,
        'verified_at' => now(),
    ]);

    return $user;
}

function childDetails(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Ibrahim',
        'last_name' => 'Rasheed',
        'dob' => now()->subYears(9)->toDateString(),
        'gender' => 'male',
        'id_type' => 'national_id',
        'national_id' => 'a654321',
    ], $overrides);
}

function legacyRowCounts(): array
{
    return [
        'registration_students' => DB::table('archived_registration_students')->count(),
        'student_guardians' => DB::table('archived_student_guardians')->count(),
    ];
}

it('registers a parent\'s child once, however often the parent registers them', function () {
    $parent = registeringParent();
    $first = Course::factory()->create(['registration_fee_amount' => 0, 'requires_admin_approval' => false]);
    $second = Course::factory()->create(['registration_fee_amount' => 0, 'requires_admin_approval' => false]);

    $service = app(EnrollmentService::class);
    $service->enrollByParent($parent, childDetails(), [$first->id], null, ['relationship' => 'mother']);
    // Typed again for a second course, the ID card in another case.
    $service->enrollByParent($parent, childDetails(['national_id' => 'A654321']), [$second->id], null, ['relationship' => 'mother']);

    $child = Student::query()->where('national_id', 'A654321')->sole();

    expect(CourseEnrollment::query()->where('unified_student_id', $child->id)->count())->toBe(2)
        ->and($child->guardians()->count())->toBe(1)
        ->and(legacyRowCounts())->toBe(['registration_students' => 0, 'student_guardians' => 0]);
});

it('enrols an existing child by their students id, and refuses somebody else\'s', function () {
    $parent = registeringParent();
    $course = Course::factory()->create(['registration_fee_amount' => 0, 'requires_admin_approval' => false]);
    $child = app(RegisterCourseStudentAction::class)->forChild($parent->id, childDetails(['national_id' => 'A111222']), 'father');

    $result = app(EnrollmentService::class)->enrollByParent($parent, $child['id'], [$course->id], null);

    expect($result->createdEnrollments)->toHaveCount(1)
        ->and($result->createdEnrollments[0]->unified_student_id)->toBe($child['id']);

    $stranger = registeringParent();

    expect(fn () => app(EnrollmentService::class)->enrollByParent($stranger, $child['id'], [$course->id], null))
        ->toThrow(ValidationException::class);
});

it('gives a child their own login on their student record', function () {
    Role::findOrCreate('student', 'web');
    $parent = registeringParent();
    $course = Course::factory()->create(['registration_fee_amount' => 0, 'requires_admin_approval' => false]);

    app(EnrollmentService::class)->enrollByParent(
        $parent,
        childDetails(['national_id' => 'A333444']),
        [$course->id],
        null,
        ['relationship' => 'father', 'child_password' => 'a-strong-password'],
    );

    $child = Student::query()->where('national_id', 'A333444')->sole();

    expect($child->user_id)->not->toBeNull()
        ->and(User::query()->find($child->user_id)?->national_id)->toBe('A333444')
        ->and(legacyRowCounts()['registration_students'])->toBe(0);

    // The child's forgot-password code goes to the parent's phone, found
    // through the child's guardian; nothing had to be copied onto the child.
    $parentMobile = $parent->contacts()->where('type', 'mobile')->sole();

    $this->withoutLocalizationMiddleware()
        ->post('/forgot-password', ['identifier' => 'A333444'])
        ->assertSessionHas('password_reset_user_id', $child->user_id)
        ->assertSessionHas('password_reset_contact_id', $parentMobile->id);
});

it('enrols a pupil on an offering without a legacy registration row', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = Course::factory()->create();
    $offering = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Batch '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
    ]);
    $student = makeStudent();

    $enrolment = app(EnrollUnifiedStudentInOfferingAction::class)->execute($student->id, $course->id, $offering->id, $admin->id);

    expect($enrolment->unified_student_id)->toBe($student->id)
        ->and(legacyRowCounts()['registration_students'])->toBe(0);
});

it('no longer reaches for the legacy student model or the dual write', function () {
    $service = file_get_contents(app_path('Domains/Admissions/Services/Enrollment/EnrollmentService.php'));

    expect($service)->not->toContain('RegistrationStudent')
        ->and($service)->not->toContain('DualWrite')
        ->and(class_exists('App\\Domains\\People\\Actions\\DualWriteCourseStudentAction'))->toBeFalse()
        ->and(class_exists('App\\Domains\\People\\Actions\\LinkGuardianDualWriteAction'))->toBeFalse()
        ->and(class_exists('App\\Domains\\People\\Actions\\EnsureLegacyStudentForUnifiedAction'))->toBeFalse()
        // Slice 3: the model itself is gone with its table.
        ->and(class_exists('App\\Domains\\People\\Models\\RegistrationStudent'))->toBeFalse();
});
