<?php

use App\Domains\Courses\Actions\ListPublishedCoursesAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Actions\DefaultSelfLearningOfferingAction;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\People\Models\RegistrationStudent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fakeP44BmlProvider(): void
{
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay/p44');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(
                verified: true,
                merchantReference: (string) $request->input('reference'),
                providerReference: 'BML-P44',
                status: 'completed',
                rawPayload: $request->all(),
                isConfirmed: true,
            );
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return null;
        }
    });
}

function seedOverrideCourse(float $courseFee): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $studentUser = User::factory()->create();
    makeStudent(['user_id' => $studentUser->id, 'first_name' => 'Sana']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Override Course '.$courseFee,
        'subject_id' => CourseSubject::query()->where('slug', 'tajweed')->value('id'),
        'created_by' => $admin->id,
    ]);
    $course->registration_fee_amount = $courseFee;
    $course->requires_admin_approval = false;
    $course->save();
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    return ['admin' => $admin, 'studentUser' => $studentUser, 'course' => $course->fresh()];
}

it('charges the offering price override instead of the course fee', function () {
    $ctx = seedOverrideCourse(100);
    fakeP44BmlProvider();

    // Publishing auto-created the default self-learning offering — override it.
    $offering = app(DefaultSelfLearningOfferingAction::class)->execute($ctx['course']->id);
    CourseOffering::query()->whereKey($offering['id'])->update(['price_override' => 60]);

    $row = collect(app(ListPublishedCoursesAction::class)->execute())
        ->firstWhere('id', $ctx['course']->id);
    expect($row['fee'])->toBe(60.0);

    $this->withoutLocalizationMiddleware()->actingAs($ctx['studentUser'])
        ->post(route('learn.courses.enroll', $ctx['course']->id))
        ->assertRedirect('https://bml.test/pay/p44');

    expect((string) Payment::query()->firstOrFail()->amount)->toBe('60.00');
});

it('treats a zero override as a free offering of a paid course', function () {
    $ctx = seedOverrideCourse(150);

    $offering = app(DefaultSelfLearningOfferingAction::class)->execute($ctx['course']->id);
    CourseOffering::query()->whereKey($offering['id'])->update(['price_override' => 0]);

    $this->withoutLocalizationMiddleware()->actingAs($ctx['studentUser'])
        ->post(route('learn.courses.enroll', $ctx['course']->id))
        ->assertRedirect(route('learn.courses.show', $ctx['course']->id));

    $enrollment = CourseEnrollment::query()->firstOrFail();
    expect($enrollment->status)->toBe('active')
        ->and($enrollment->payment_status)->toBe('not_required')
        ->and(Payment::query()->count())->toBe(0);
});

it('records a manual payment that activates the enrollment through the single path', function () {
    $payer = User::factory()->create();
    $student = RegistrationStudent::create([
        'user_id' => $payer->id,
        'first_name' => 'Ahmed',
        'last_name' => 'Rasheed',
        'dob' => now()->subYears(30),
    ]);
    $course = Course::factory()->create([
        'registration_fee_amount' => 80,
        'requires_admin_approval' => false,
    ]);
    $enrollment = CourseEnrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);
    $admin = actingPeopleAdmin(['payments.record']);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.enrollments.record-payment', $enrollment->id), [
            'amount' => 80,
            'note' => 'Cash at office',
        ])->assertSessionHasNoErrors()->assertRedirect();

    $payment = Payment::query()->firstOrFail();
    expect($payment->provider)->toBe('manual')
        ->and($payment->status)->toBe('confirmed')
        ->and($payment->getRawOriginal('payable_type'))->toBe('course_enrollment')
        ->and((int) $payment->payable_id)->toBe($enrollment->id)
        ->and((int) $payment->user_id)->toBe($payer->id)
        ->and($payment->notes)->toContain('Cash at office');

    $enrollment->refresh();
    expect($enrollment->status)->toBe('active')
        ->and($enrollment->payment_status)->toBe('confirmed')
        ->and($enrollment->enrolled_at)->not->toBeNull()
        ->and((int) $enrollment->payment_id)->toBe($payment->id);
});

it('exports the payments listing as CSV', function () {
    $admin = actingPeopleAdmin();

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.enrollments.payments.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
