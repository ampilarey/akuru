<?php

use App\Domains\Courses\Actions\StartCourseCheckoutAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Actions\InitiatePayablePaymentAction;
use App\Domains\Finance\Actions\ListManualPaymentMethodsAction;
use App\Domains\Finance\Actions\RecordManualPaymentAction;
use App\Domains\Finance\Enums\PaymentMethod;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentService;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Actions\SaveCourseOfferingAction;
use App\Domains\Website\Models\FunnelEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §38 "Payment-Ready Design" lists the fields the payments table must
 * carry. Three did not exist and two were never filled.
 *
 * **Course offering ID** was absent, in a section that ends "Offerings may
 * override course price" — so what a student paid could differ per offering
 * and the payment could not say which offering it was for.
 *
 * **Payment method** was absent. §38 lists it *separately* from Gateway, and
 * rule 12 makes that distinction load bearing. All the table had was
 * `provider` (bml | manual); how the money actually arrived survived only as
 * English prose in `notes`, prompted by a form placeholder that read
 * "Note (e.g. cash at office)".
 *
 * **Metadata JSON** was absent — four BML-owned payload columns and nowhere
 * for a caller to say what the payment was for.
 *
 * And **student_id / course_id were left null by the engine path**, though the
 * columns existed and the legacy public checkout filled them. That one had a
 * consequence beyond §38's field list, which the funnel test below pins down.
 */
uses(RefreshDatabase::class);

function paymentContextCourse(float $fee = 250.0): array
{
    $payer = User::factory()->create();
    // `EnrollSelfLearningAction` resolves the payer through the People
    // `students` table, so the payer needs a unified student row, not only a
    // registration one.
    $unified = makeStudent(['user_id' => $payer->id, 'first_name' => 'Paying', 'last_name' => 'Student']);
    $student = makeRegistrationStudent(['user_id' => $payer->id]);
    $course = Course::factory()->create([
        'registration_fee_amount' => $fee,
        'requires_admin_approval' => false,
        'workflow_status' => 'published',
        'status' => 'open',
    ]);

    return ['payer' => $payer, 'student' => $student, 'unified' => $unified, 'course' => $course];
}

/**
 * A real pending enrollment to record money against — `PaymentConfirmed`
 * activates it through the same listener a webhook would, so it has to exist.
 */
function paymentContextEnrollment(int $courseId, int $studentId): CourseEnrollment
{
    return CourseEnrollment::query()->create([
        'course_id' => $courseId,
        'student_id' => $studentId,
        'status' => 'pending',
        'payment_status' => 'pending',
        'enrollment_type' => 'paid',
    ]);
}

it('carries every payment field SPEC §38 names', function () {
    foreach ([
        'student_id', 'course_id', 'course_offering_id', 'amount', 'currency',
        'payment_method', 'provider', 'status', 'provider_reference', 'paid_at',
        'metadata', 'created_at', 'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('payments', $column))->toBeTrue("payments.{$column} is missing");
    }
});

it('keeps the payable morph as the only record of which enrollment paid', function () {
    // §38 also lists "Enrollment ID". It is deliberately not a column:
    // `payable_type`/`payable_id` already carries it, `course_enrollment` is a
    // registered morph alias, and a second column for the same fact is the
    // drift rule 11 exists to stop.
    expect(Schema::hasColumn('payments', 'enrollment_id'))->toBeFalse()
        ->and(config('morph-map.course_enrollment'))
        ->toBe(CourseEnrollment::class);
});

it('records the course, the offering and the student on an engine checkout', function () {
    ['payer' => $payer, 'course' => $course] = paymentContextCourse();
    $offering = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Paid batch '.uniqueFixtureSuffix(),
        'delivery_mode' => 'live_online',
        'status' => 'open',
    ]);

    app(StartCourseCheckoutAction::class)->execute($payer->id, $course->id, $offering->id);

    $payment = Payment::query()->latest('id')->first();

    expect($payment)->not->toBeNull()
        ->and((int) $payment->course_id)->toBe((int) $course->id)
        ->and((int) $payment->course_offering_id)->toBe((int) $offering->id)
        ->and($payment->payable_type)->toBe('course_enrollment')
        ->and($payment->metadata['source'] ?? null)->toBe('course_engine_checkout')
        ->and($payment->metadata['list_price'] ?? null)->toEqual(250.0);
});

it('counts an engine course purchase in the conversion funnel', function () {
    // The consequence that made this more than a missing field.
    // `PaymentService::recordPaymentCompletedFunnel()` resolves the course
    // from `payment->course_id` or from `payment_items`. An engine payment had
    // neither — no course id, and it creates no items — so **every course
    // bought through the engine recorded no `payment_completed` event at
    // all**, and the W1.1 conversion reporting silently under-counted them.
    ['payer' => $payer, 'course' => $course] = paymentContextCourse();

    app(StartCourseCheckoutAction::class)->execute($payer->id, $course->id);
    $payment = Payment::query()->latest('id')->first();

    // Confirm it the way a webhook would, through the same private path.
    $service = app(PaymentService::class);
    $record = new ReflectionMethod($service, 'recordPaymentCompletedFunnel');
    $record->invoke($service, $payment->fresh());

    expect(FunnelEvent::query()->where('course_id', $course->id)->where('name', 'payment_completed')->count())
        ->toBe(1);
});

it('records how the money arrived when an admin takes it by hand', function () {
    ['payer' => $payer, 'student' => $student, 'course' => $course] = paymentContextCourse();
    $enrollment = paymentContextEnrollment($course->id, $student->id);

    $payment = app(RecordManualPaymentAction::class)->execute(
        'course_enrollment',
        $enrollment->id,
        $payer->id,
        250.0,
        'Receipt 4471',
        $payer->id,
        PaymentMethod::BankTransfer->value,
        ['course_id' => $course->id],
    );

    expect($payment->payment_method)->toBe(PaymentMethod::BankTransfer)
        ->and($payment->provider)->toBe('manual')
        ->and((int) $payment->course_id)->toBe((int) $course->id)
        ->and($payment->status)->toBe('confirmed');
});

it('answers "how much cash came through the office" from the data, not from prose', function () {
    // The point of a method column: this query was impossible before, because
    // the only record of "cash" was a free-text note an admin may or may not
    // have written.
    ['payer' => $payer, 'student' => $student, 'course' => $course] = paymentContextCourse();
    $record = app(RecordManualPaymentAction::class);
    $a = paymentContextEnrollment($course->id, $student->id);
    $b = paymentContextEnrollment($course->id, makeRegistrationStudent()->id);
    $c = paymentContextEnrollment($course->id, makeRegistrationStudent()->id);

    $record->execute('course_enrollment', $a->id, $payer->id, 100.0, null, null, PaymentMethod::Cash->value);
    $record->execute('course_enrollment', $b->id, $payer->id, 50.0, null, null, PaymentMethod::Cash->value);
    $record->execute('course_enrollment', $c->id, $payer->id, 300.0, null, null, PaymentMethod::BankTransfer->value);

    expect((float) Payment::query()->where('payment_method', PaymentMethod::Cash)->sum('amount'))->toBe(150.0)
        ->and((float) Payment::query()->where('payment_method', PaymentMethod::BankTransfer)->sum('amount'))->toBe(300.0);
});

it('refuses to let an admin hand-record wallet or gift-card money', function () {
    // Rule 11: that money is spent inside the product and the Commerce ledger
    // already records it. A hand-entered copy would be a second record of the
    // same money.
    ['payer' => $payer] = paymentContextCourse();

    expect(fn () => app(RecordManualPaymentAction::class)->execute(
        'course_enrollment', 1, $payer->id, 100.0, null, null, PaymentMethod::Wallet->value,
    ))->toThrow(ValidationException::class)
        ->and(fn () => app(RecordManualPaymentAction::class)->execute(
            'course_enrollment', 1, $payer->id, 100.0, null, null, PaymentMethod::GiftCard->value,
        ))->toThrow(ValidationException::class);
});

it('leaves a gateway payment method null rather than guessing an instrument', function () {
    // BML Connect does not report an instrument back. Stamping every gateway
    // payment "card" would record a guess as a fact.
    ['payer' => $payer, 'course' => $course] = paymentContextCourse();

    app(StartCourseCheckoutAction::class)->execute($payer->id, $course->id);

    expect(Payment::query()->latest('id')->first()->payment_method)->toBeNull();
});

it('does not turn an absent context id into zero', function () {
    ['payer' => $payer] = paymentContextCourse();

    $result = app(InitiatePayablePaymentAction::class)->execute(
        'course_enrollment',
        1,
        $payer->id,
        100.0,
        'MVR',
        null,
        ['course_id' => null, 'course_offering_id' => '', 'metadata' => []],
    );

    expect($result['payment']->course_id)->toBeNull()
        ->and($result['payment']->course_offering_id)->toBeNull()
        ->and($result['payment']->metadata)->toBeNull();
});

it('requires the admin form to say which method it was', function () {
    ['course' => $course] = paymentContextCourse();
    $admin = actingPeopleAdmin(['payments.record', 'enrollments.manage']);
    $enrollment = paymentContextEnrollment($course->id, makeRegistrationStudent()->id);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/admin/enrollments/{$enrollment->id}/record-payment", ['amount' => 250])
        ->assertSessionHasErrors('payment_method');
});

it('keeps the method vocabulary inside Finance, reachable only through an Action', function () {
    // The first version of this slice imported `Finance\Enums\PaymentMethod`
    // straight into the Admissions controller, and
    // `BaselineArchitectureTest` refused it: rule 3 allows cross-domain
    // traffic through Contracts/DTOs/Events/Actions only, and an enum is none
    // of those. The Action is the seam, so the enum cannot leak again without
    // this going red alongside the arch suite.
    $controller = file_get_contents(base_path('app/Domains/Admissions/Http/Controllers/AdminEnrollmentController.php'));

    expect($controller)->not->toContain('Finance\Enums\PaymentMethod')
        ->and(app(ListManualPaymentMethodsAction::class)->values())
        ->toBe(['cash', 'bank_transfer', 'cheque', 'card', 'other']);
});

it('refuses a method string that is not in the vocabulary', function () {
    ['payer' => $payer] = paymentContextCourse();

    expect(fn () => app(RecordManualPaymentAction::class)->execute(
        'course_enrollment', 1, $payer->id, 100.0, null, null, 'bitcoin',
    ))->toThrow(ValidationException::class);
});
