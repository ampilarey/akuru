<?php

use App\Domains\Courses\Actions\ListCourseLearningAction;
use App\Domains\Courses\Actions\ResolveCourseCertificateStatusAction;
use App\Domains\Courses\Models\CertificateTemplate;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SPEC §24 lists "Certificate eligibility status" among what the Course
 * Learning Page must show.
 *
 * The rules engine was complete — `CheckCertificateEligibilityAction` weighs
 * minimum progress, payment, teacher approval, attendance and score, with
 * course rules overridden at offering level — and had exactly **one** caller:
 * `IssueCertificateAction`. So it ran once, at the moment an admin issued the
 * certificate. A student working toward one could not see whether they were on
 * track, and more to the point could not see **what was missing**, which is the
 * only part they can act on.
 */
uses(RefreshDatabase::class);

function certificateCourse(): Course
{
    return Course::query()->create([
        'course_category_id' => DB::table('course_categories')->insertGetId([
            'name' => 'Certificates', 'slug' => 'certificates-'.Str::random(6), 'order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'title' => 'Certificate course',
        'slug' => 'certificate-course-'.Str::random(6),
        'short_desc' => 'x', 'body' => 'y', 'cover_image' => '',
        'workflow_status' => 'published', 'course_type' => 'general', 'status' => 'open',
    ]);
}

function certificateTemplateFor(Course $course, array $rules): CertificateTemplate
{
    return CertificateTemplate::query()->create([
        'name' => 'Course completion',
        'kind' => 'course_completion',
        'course_id' => $course->id,
        'rules' => $rules,
        'body_html' => '<p>Well done</p>',
        'active' => true,
    ]);
}

function enrolStudentAt(Course $course, int $studentId, int $progress): CourseEnrollment
{
    return CourseEnrollment::query()->create([
        'course_id' => $course->id,
        // `student_id` FKs to `registration_students`; `unified_student_id` is
        // the People `students` row, and is what every lookup here matches on.
        'student_id' => makeRegistrationStudent()->id,
        'unified_student_id' => $studentId,
        'status' => 'active',
        'payment_status' => 'not_required',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => $progress,
    ]);
}

it('returns nothing when the course issues no certificate', function () {
    $course = certificateCourse();
    $student = makeStudent(['first_name' => 'No', 'last_name' => 'Certificate']);
    $enrollment = enrolStudentAt($course, $student->id, 100);

    $status = app(ResolveCourseCertificateStatusAction::class)
        ->execute((int) $course->id, $enrollment, (int) $student->id);

    // Most courses do not award one; the section must stay off rather than
    // showing an empty box.
    expect($status)->toBeNull();
});

it('tells a student exactly what is still missing', function () {
    $course = certificateCourse();
    certificateTemplateFor($course, ['min_progress_percent' => 80]);
    $student = makeStudent(['first_name' => 'Partway', 'last_name' => 'Through']);
    $enrollment = enrolStudentAt($course, $student->id, 40);

    $status = app(ResolveCourseCertificateStatusAction::class)
        ->execute((int) $course->id, $enrollment, (int) $student->id);

    // "Not yet" is discouraging; "your progress is below the minimum" is
    // something a student can do something about.
    expect($status['eligible'])->toBeFalse()
        ->and($status['issued'])->toBeFalse()
        ->and($status['reasons'])->toContain('Progress is below the minimum.');
});

it('says so when the student has met the requirements', function () {
    $course = certificateCourse();
    certificateTemplateFor($course, ['min_progress_percent' => 50]);
    $student = makeStudent(['first_name' => 'Ready', 'last_name' => 'Student']);
    $enrollment = enrolStudentAt($course, $student->id, 90);

    $status = app(ResolveCourseCertificateStatusAction::class)
        ->execute((int) $course->id, $enrollment, (int) $student->id);

    expect($status['eligible'])->toBeTrue()
        ->and($status['reasons'])->toBeEmpty();
});

it('surfaces teacher approval as an outstanding requirement', function () {
    $course = certificateCourse();
    certificateTemplateFor($course, ['require_teacher_approval' => true]);
    $student = makeStudent(['first_name' => 'Awaiting', 'last_name' => 'Signoff']);
    $enrollment = enrolStudentAt($course, $student->id, 100);

    $status = app(ResolveCourseCertificateStatusAction::class)
        ->execute((int) $course->id, $enrollment, (int) $student->id);

    // The student view asserts no approval, so a course needing sign-off
    // reports it as outstanding — which is true, and worth telling them.
    expect($status['eligible'])->toBeFalse()
        ->and($status['reasons'])->toContain('Teacher approval is required.');
});

it('reports a certificate the student already holds without re-judging it', function () {
    $course = certificateCourse();
    $template = certificateTemplateFor($course, ['min_progress_percent' => 90]);
    $student = makeStudent(['first_name' => 'Already', 'last_name' => 'Earned']);
    // Progress below the rule on purpose: the thresholds may have been raised
    // after the certificate was awarded, and a student holding one should not
    // be told they are ineligible for it.
    $enrollment = enrolStudentAt($course, $student->id, 10);

    DB::table('issued_certificates')->insert([
        'certificate_template_id' => $template->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'academic_year_id' => makeYear()->id,
        'public_id' => Str::random(24),
        'certificate_number' => 'CERT-0001',
        'completion_date' => now()->toDateString(),
        'issued_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $status = app(ResolveCourseCertificateStatusAction::class)
        ->execute((int) $course->id, $enrollment, (int) $student->id);

    expect($status['issued'])->toBeTrue()
        ->and($status['eligible'])->toBeTrue()
        ->and($status['certificate_number'])->toBe('CERT-0001');
});

it('does not count a revoked certificate as earned', function () {
    $course = certificateCourse();
    $template = certificateTemplateFor($course, ['min_progress_percent' => 90]);
    $student = makeStudent(['first_name' => 'Revoked', 'last_name' => 'Holder']);
    $enrollment = enrolStudentAt($course, $student->id, 10);

    DB::table('issued_certificates')->insert([
        'certificate_template_id' => $template->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'academic_year_id' => makeYear()->id,
        'public_id' => Str::random(24),
        'certificate_number' => 'CERT-0002',
        'completion_date' => now()->toDateString(),
        'issued_at' => now()->subDay(),
        'revoked_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $status = app(ResolveCourseCertificateStatusAction::class)
        ->execute((int) $course->id, $enrollment, (int) $student->id);

    expect($status['issued'])->toBeFalse()
        ->and($status['eligible'])->toBeFalse();
});

it('puts the certificate status on the learning page payload', function () {
    $course = certificateCourse();
    certificateTemplateFor($course, ['min_progress_percent' => 80]);
    $user = User::factory()->create();
    $student = makeStudent(['user_id' => $user->id, 'first_name' => 'Learning', 'last_name' => 'Page']);
    enrolStudentAt($course, $student->id, 20);

    $payload = app(ListCourseLearningAction::class)->execute((int) $course->id, (int) $user->id);

    // The engine existed all along; what was missing was this line.
    expect($payload)->toHaveKey('certificate')
        ->and($payload['certificate']['eligible'])->toBeFalse()
        ->and($payload)->toHaveKey('offering');
});
