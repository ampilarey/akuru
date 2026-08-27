<?php

use App\Domains\Commerce\Actions\CreditWalletAction;
use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Commerce\Models\DiscountRedemption;
use App\Domains\Commerce\Models\Wallet;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fakeCourseBmlProvider(): void
{
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay/course');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(
                verified: true,
                merchantReference: (string) $request->input('reference'),
                providerReference: 'BML-P4',
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

function seedPaidCourse(float $fee = 100, bool $requiresApproval = false): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $studentUser = User::factory()->create();
    makeStudent(['user_id' => $studentUser->id, 'first_name' => 'Zahra']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Paid Tajweed Course',
        'subject_id' => CourseSubject::query()->where('slug', 'tajweed')->value('id'),
        'created_by' => $admin->id,
    ]);
    $course->registration_fee_amount = $fee;
    $course->requires_admin_approval = $requiresApproval;
    $course->save();
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    return ['admin' => $admin, 'studentUser' => $studentUser, 'course' => $course->fresh()];
}

it('sells an engine enrollment through BML and activates it only on the webhook', function () {
    $ctx = seedPaidCourse(100);
    fakeCourseBmlProvider();

    $this->withoutLocalizationMiddleware()->actingAs($ctx['studentUser'])
        ->post(route('learn.courses.enroll', $ctx['course']->id))
        ->assertRedirect('https://bml.test/pay/course');

    $enrollment = CourseEnrollment::query()->firstOrFail();
    $payment = Payment::query()->firstOrFail();
    expect($enrollment->status)->toBe('pending')
        ->and($enrollment->payment_status)->toBe('pending')
        ->and($enrollment->enrollment_type)->toBe('paid')
        ->and($payment->getRawOriginal('payable_type'))->toBe('course_enrollment')
        ->and((int) $payment->payable_id)->toBe($enrollment->id)
        ->and((string) $payment->amount)->toBe('100.00');

    // Webhook confirms -> active; a retry stays idempotent.
    foreach ([1, 2] as $round) {
        $this->postJson(url('/webhooks/bml'), [
            'reference' => $payment->merchant_reference,
            'transactionId' => 'BML-P4',
            'status' => 'completed',
        ])->assertStatus(200);
    }
    $enrollment->refresh();
    expect($enrollment->status)->toBe('active')
        ->and($enrollment->payment_status)->toBe('confirmed')
        ->and($enrollment->enrolled_at)->not->toBeNull();
    expect(CourseEnrollment::query()->count())->toBe(1);
});

it('pays for a course from the wallet with a discount, activating immediately', function () {
    $ctx = seedPaidCourse(200);
    app(CreditWalletAction::class)->execute($ctx['studentUser']->id, 300, 'admin');
    app(SaveDiscountCodeAction::class)->execute([
        'code' => 'COURSE50',
        'discount_type' => 'fixed',
        'discount_value' => 50,
    ]);

    $this->withoutLocalizationMiddleware()->actingAs($ctx['studentUser'])
        ->post(route('learn.courses.enroll', $ctx['course']->id), [
            'discount_code' => 'COURSE50',
            'pay_with_wallet' => 1,
        ])
        ->assertRedirect(route('learn.courses.show', $ctx['course']->id));

    $enrollment = CourseEnrollment::query()->firstOrFail();
    expect($enrollment->status)->toBe('active')
        ->and($enrollment->payment_status)->toBe('confirmed')
        ->and(Payment::query()->count())->toBe(0)
        ->and((string) Wallet::query()->where('user_id', $ctx['studentUser']->id)->value('balance'))->toBe('150.00');
    expect(DiscountRedemption::query()->firstOrFail()->status)->toBe('confirmed');

    // Re-enrolling never double-charges an already-held enrollment.
    $this->withoutLocalizationMiddleware()->actingAs($ctx['studentUser'])
        ->post(route('learn.courses.enroll', $ctx['course']->id))
        ->assertRedirect(route('learn.courses.show', $ctx['course']->id));
    expect((string) Wallet::query()->where('user_id', $ctx['studentUser']->id)->value('balance'))->toBe('150.00')
        ->and(CourseEnrollment::query()->count())->toBe(1);
});

it('keeps free courses free and holds approval-gated paid courses at pending', function () {
    // Free course: unchanged behavior.
    $free = seedPaidCourse(0);
    $this->withoutLocalizationMiddleware()->actingAs($free['studentUser'])
        ->post(route('learn.courses.enroll', $free['course']->id))
        ->assertRedirect(route('learn.courses.show', $free['course']->id));
    $enrollment = CourseEnrollment::query()->firstOrFail();
    expect($enrollment->status)->toBe('active')
        ->and($enrollment->enrollment_type)->toBe('free')
        ->and($enrollment->payment_status)->toBe('not_required')
        ->and(Payment::query()->count())->toBe(0);

    // Approval-gated paid course: money confirms, status waits for admin.
    $gated = seedPaidCourse(80, requiresApproval: true);
    fakeCourseBmlProvider();
    $this->withoutLocalizationMiddleware()->actingAs($gated['studentUser'])
        ->post(route('learn.courses.enroll', $gated['course']->id))
        ->assertRedirect('https://bml.test/pay/course');
    $paidEnrollment = CourseEnrollment::query()
        ->where('course_id', $gated['course']->id)
        ->firstOrFail();
    $payment = Payment::query()->firstOrFail();
    $this->postJson(url('/webhooks/bml'), [
        'reference' => $payment->merchant_reference,
        'transactionId' => 'BML-P4',
        'status' => 'completed',
    ])->assertStatus(200);
    $paidEnrollment->refresh();
    expect($paidEnrollment->payment_status)->toBe('confirmed')
        ->and($paidEnrollment->status)->toBe('pending');
});
