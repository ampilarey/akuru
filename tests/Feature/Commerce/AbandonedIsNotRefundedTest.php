<?php

use App\Domains\Commerce\Actions\RecordDiscountRedemptionAction;
use App\Domains\Commerce\Actions\ResolveDiscountAction;
use App\Domains\Commerce\Actions\SaveDiscountCodeAction;
use App\Domains\Commerce\Models\DiscountCode;
use App\Domains\Commerce\Models\DiscountRedemption;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A discount slot given back because the payment never came is told apart from
 * one given back by a refund (STATUS §5pq).
 *
 * Both wrote `released`. So a course payment landing after the hourly prune had
 * cancelled its enrolment could not take its slot back: a `released` row on an
 * enrolment may be a refund's, and an enrolment is handed back to every retry,
 * so it may also be an earlier attempt's. The code stayed free for one use
 * more (KNOWN_ISSUES, left open by §5pn). Abandonment now writes `abandoned`.
 * A late payment confirms the pending row if there is one, otherwise the latest
 * abandoned row, and never a refund's.
 */
uses(RefreshDatabase::class);

function pqCode(string $code): DiscountCode
{
    return app(SaveDiscountCodeAction::class)->execute([
        'code' => $code,
        'discount_type' => 'fixed',
        'discount_value' => 10,
        'per_user_limit' => 1,
    ]);
}

function pqCodeIsFree(string $code, int $userId): bool
{
    try {
        app(ResolveDiscountAction::class)->execute($code, $userId, 100.0);

        return true;
    } catch (ValidationException) {
        return false;
    }
}

function pqRedemption(DiscountCode $code, int $userId, int $purchaseId, string $status): DiscountRedemption
{
    $row = app(RecordDiscountRedemptionAction::class)->execute($code->id, $userId, 'course_enrollment', $purchaseId, 10.0);
    $row->status = $status;
    $row->save();

    return $row;
}

it('takes a course\'s slot back when its payment lands after the prune', function () {
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay/late');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(verified: true, merchantReference: (string) $request->input('reference'), providerReference: 'BML-LATE', status: 'completed', rawPayload: $request->all(), isConfirmed: true);
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return null;
        }
    });
    $admin = actingPeopleAdmin(['courses.manage']);
    $learner = User::factory()->create();
    makeStudent(['user_id' => $learner->id, 'first_name' => 'Hawwa']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Late Paid Course',
        'subject_id' => CourseSubject::query()->where('slug', 'tajweed')->value('id'),
        'created_by' => $admin->id,
    ]);
    $course->registration_fee_amount = 120;
    $course->requires_admin_approval = false;
    $course->save();
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);
    pqCode('COURSELATE');

    $this->withoutLocalizationMiddleware()->actingAs($learner)
        ->post(route('learn.courses.enroll', $course->id), ['discount_code' => 'COURSELATE'])
        ->assertRedirect('https://bml.test/pay/late');
    $enrollment = CourseEnrollment::query()->sole();

    DB::table('course_enrollments')->where('id', $enrollment->id)->update(['created_at' => now()->subHours(25)]);
    $this->artisan('akuru:prune-expired')->assertExitCode(0);
    expect($enrollment->fresh()->status)->toBe('cancelled')
        ->and(DiscountRedemption::query()->sole()->status)->toBe('abandoned')
        ->and(pqCodeIsFree('COURSELATE', $learner->id))->toBeTrue();

    $this->postJson(url('/webhooks/bml'), [
        'reference' => Payment::query()->sole()->merchant_reference,
        'transactionId' => 'BML-LATE',
        'status' => 'completed',
    ])->assertOk();

    expect($enrollment->fresh()->payment_status)->toBe('confirmed')
        ->and(DiscountRedemption::query()->sole()->status)->toBe('confirmed')
        ->and(pqCodeIsFree('COURSELATE', $learner->id))->toBeFalse();
});

it('never takes back the slot a refund gave back', function () {
    $code = pqCode('REFUNDED');
    $buyer = User::factory()->create();
    $row = pqRedemption($code, $buyer->id, 41, 'confirmed');

    app(RecordDiscountRedemptionAction::class)->releaseForRefund('course_enrollment', 41);
    expect(app(RecordDiscountRedemptionAction::class)->confirmLanded('course_enrollment', 41))->toBe(0)
        ->and($row->fresh()->status)->toBe('released')
        ->and(pqCodeIsFree('REFUNDED', $buyer->id))->toBeTrue();
});

it('confirms the retry\'s pending use, and leaves an earlier attempt\'s abandoned one', function () {
    $code = pqCode('RETRY');
    $buyer = User::factory()->create();
    $earlier = pqRedemption($code, $buyer->id, 42, 'abandoned');
    $retry = pqRedemption($code, $buyer->id, 42, 'pending');

    expect(app(RecordDiscountRedemptionAction::class)->confirmLanded('course_enrollment', 42))->toBe(1)
        ->and($retry->fresh()->status)->toBe('confirmed')
        ->and($earlier->fresh()->status)->toBe('abandoned');
});

it('takes back only the latest abandoned use when nothing is pending', function () {
    $code = pqCode('TWICE');
    $buyer = User::factory()->create();
    $first = pqRedemption($code, $buyer->id, 43, 'abandoned');
    $second = pqRedemption($code, $buyer->id, 43, 'abandoned');

    expect(app(RecordDiscountRedemptionAction::class)->confirmLanded('course_enrollment', 43))->toBe(1)
        ->and($second->fresh()->status)->toBe('confirmed')
        ->and($first->fresh()->status)->toBe('abandoned')
        ->and(DiscountRedemption::query()->where('status', 'confirmed')->count())->toBe(1);
});
