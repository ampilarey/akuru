<?php

use App\Domains\Courses\Actions\CheckCertificateEligibilityAction;
use App\Domains\Courses\Actions\NormalizeCertificateRulesAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Models\CertificateTemplate;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Offerings\Actions\SaveCourseOfferingAction;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * SPEC §39 "Certificate Eligibility Rules":
 *
 *   > Certificate rules may be set at course level and overridden at offering
 *   > level.
 *
 * The override half did not work, and every piece of it existed except the one
 * that mattered.
 *
 * `course_offerings.certificate_rules` is a real column with a real cast.
 * `SaveCourseOfferingAction` assigned it. `GetOfferingCertificateRulesAction`
 * read it. `CheckCertificateEligibilityAction` layered it over the template's
 * rules. What was missing was the field in `CourseOfferingController::
 * validated()` — and `$request->validate()` returns only the keys it
 * validates, so the value was discarded on the way in no matter how it was
 * posted. Nothing could set it: not the form, not the API, not an admin.
 *
 * This is the sixth instance of the same shape in this sweep — a field that
 * reaches the migration, the model and the Action, and stops at the form.
 * Tests never catch it because tests call the Action directly, so the first
 * test below goes through HTTP on purpose.
 */
uses(RefreshDatabase::class);

function ruleOverrideCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Override '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);

    return ['admin' => $admin, 'course' => $course];
}

it('stores an offering-level override posted through the offering form', function () {
    // The test the gap needed. An Action-level test would have passed against
    // the broken code, because the Action was never the broken part.
    ['admin' => $admin, 'course' => $course] = ruleOverrideCourse();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/offerings', [
            'course_id' => $course->id,
            'title' => 'Strict batch',
            'delivery_mode' => 'face_to_face',
            'certificate_rules' => [
                'min_progress_percent' => 90,
                'require_teacher_approval' => '1',
            ],
        ])
        ->assertRedirect();

    expect(CourseOffering::query()->latest('id')->first()->certificate_rules)
        ->toBe(['min_progress_percent' => 90, 'require_teacher_approval' => true]);
});

it('keeps the override sparse so an unset rule inherits instead of switching off', function () {
    // The trap this shape invites. If an unticked box stored `false`, a batch
    // that only wanted a higher progress bar would silently drop the course's
    // teacher-approval and payment requirements on the way past.
    ['admin' => $admin, 'course' => $course] = ruleOverrideCourse();

    $this->actingAs($admin)->withoutLocalizationMiddleware()->post('/catalog/offerings', [
        'course_id' => $course->id,
        'title' => 'Only progress '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
        'certificate_rules' => [
            'min_progress_percent' => 80,
            'require_teacher_approval' => '',
            'require_payment' => '',
            'min_score' => '',
        ],
    ]);

    expect(CourseOffering::query()->latest('id')->first()->certificate_rules)
        ->toBe(['min_progress_percent' => 80]);
});

it('tells "not required" apart from "inherit"', function () {
    ['admin' => $admin, 'course' => $course] = ruleOverrideCourse();

    $this->actingAs($admin)->withoutLocalizationMiddleware()->post('/catalog/offerings', [
        'course_id' => $course->id,
        'title' => 'Relaxed batch '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
        'certificate_rules' => [
            'require_teacher_approval' => '0',
            'require_payment' => '',
        ],
    ]);

    $rules = CourseOffering::query()->latest('id')->first()->certificate_rules;

    expect($rules)->toHaveKey('require_teacher_approval')
        ->and($rules['require_teacher_approval'])->toBeFalse()
        ->and($rules)->not->toHaveKey('require_payment');
});

it('lets an offering relax a requirement the course template imposes', function () {
    // The end-to-end meaning of "overridden at offering level": the same
    // student, the same template, two batches, two answers.
    ['admin' => $admin, 'course' => $course] = ruleOverrideCourse();
    $student = makeStudent(['first_name' => 'Override', 'last_name' => 'Candidate']);

    $template = CertificateTemplate::query()->create([
        'name' => 'Course completion',
        'kind' => 'course_completion',
        'course_id' => $course->id,
        'rules' => ['min_progress_percent' => 100, 'require_teacher_approval' => true],
        'active' => true,
    ]);

    $strict = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Strict '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
    ]);
    $relaxed = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Relaxed '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
        'certificate_rules' => ['min_progress_percent' => 60, 'require_teacher_approval' => '0'],
    ]);

    foreach ([$strict, $relaxed] as $offering) {
        CourseEnrollment::query()->create([
            'course_id' => $course->id,
            'course_offering_id' => $offering->id,
            'student_id' => makeRegistrationStudent()->id,
            'unified_student_id' => $student->id,
            'status' => 'active',
            'payment_status' => 'not_required',
            'enrollment_type' => 'self_learning',
            'progress_percentage' => 70,
        ]);
    }

    $check = app(CheckCertificateEligibilityAction::class);

    expect($check->execute($template, $student->id, $course->id, $strict->id)['eligible'])->toBeFalse()
        ->and($check->execute($template, $student->id, $course->id, $relaxed->id)['eligible'])->toBeTrue();
});

it('lets an offering tighten a requirement the course template allows', function () {
    ['admin' => $admin, 'course' => $course] = ruleOverrideCourse();
    $student = makeStudent(['first_name' => 'Tight', 'last_name' => 'Candidate']);

    $template = CertificateTemplate::query()->create([
        'name' => 'Course completion',
        'kind' => 'course_completion',
        'course_id' => $course->id,
        'rules' => ['min_progress_percent' => 50],
        'active' => true,
    ]);

    $offering = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Tight '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
        'certificate_rules' => ['min_progress_percent' => 95],
    ]);

    CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'course_offering_id' => $offering->id,
        'student_id' => makeRegistrationStudent()->id,
        'unified_student_id' => $student->id,
        'status' => 'active',
        'payment_status' => 'not_required',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => 70,
    ]);

    $result = app(CheckCertificateEligibilityAction::class)
        ->execute($template, $student->id, $course->id, $offering->id);

    expect($result['eligible'])->toBeFalse()
        ->and($result['reasons'])->toContain('Progress is below the minimum.');
});

it('clears an override when the form posts it back empty', function () {
    ['admin' => $admin, 'course' => $course] = ruleOverrideCourse();
    $offering = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Clearable '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
        'certificate_rules' => ['min_score' => 70],
    ]);

    $this->actingAs($admin)->withoutLocalizationMiddleware()->put('/catalog/offerings/'.$offering->id, [
        'course_id' => $course->id,
        'title' => $offering->title,
        'delivery_mode' => 'face_to_face',
        'certificate_rules' => ['min_score' => '', 'require_payment' => ''],
    ]);

    expect($offering->refresh()->certificate_rules)->toBeNull();
});

it('leaves an existing override alone when the caller does not mention it', function () {
    // A save path that knows nothing about certificate rules must not wipe
    // them in passing — absent is not the same as cleared.
    ['admin' => $admin, 'course' => $course] = ruleOverrideCourse();
    $offering = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Untouched '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
        'certificate_rules' => ['min_score' => 70],
    ]);

    app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Renamed',
        'delivery_mode' => 'face_to_face',
    ], $offering);

    expect($offering->refresh()->certificate_rules)->toBe(['min_score' => 70]);
});

it('refuses an override outside the range the rule is expressed in', function () {
    ['admin' => $admin, 'course' => $course] = ruleOverrideCourse();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/offerings', [
            'course_id' => $course->id,
            'title' => 'Impossible '.uniqueFixtureSuffix(),
            'delivery_mode' => 'face_to_face',
            'certificate_rules' => ['min_progress_percent' => 150],
        ])
        ->assertSessionHasErrors('certificate_rules.min_progress_percent');
});

it('keeps one rule vocabulary for templates and overrides', function () {
    // The template shape answers every rule; the override shape answers only
    // what was asked. Same normalizer, so the two cannot drift apart.
    $normalize = app(NormalizeCertificateRulesAction::class);

    $template = $normalize->execute(['min_score' => 60]);
    $override = $normalize->execute(['min_score' => 60], sparse: true);

    expect(array_keys($template))->toBe([
        'min_progress_percent',
        'min_attendance_percent',
        'min_score',
        'assessment_id',
        'require_final_assessment',
        'require_teacher_approval',
        'require_payment',
    ])
        ->and($template['require_payment'])->toBeFalse()
        ->and($override)->toBe(['min_score' => 60])
        ->and($normalize->execute([], sparse: true))->toBeNull()
        ->and($normalize->execute(null, sparse: true))->toBeNull();
});

it('puts the override and the §11.4 states on the offerings screen', function () {
    // Both halves of this slice are only real if the screen carries them.
    ['admin' => $admin, 'course' => $course] = ruleOverrideCourse();
    app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Shown '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
        'certificate_rules' => ['min_attendance_percent' => 75],
    ]);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/offerings')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Offerings/Catalog/Index')
            ->where('rows.0.certificate_rules', ['min_attendance_percent' => 75])
            ->where('rows.0.allowed_transitions', ['open', 'cancelled', 'archived'])
            ->has('statuses', 7)
            ->has('assessments')
        );
});
