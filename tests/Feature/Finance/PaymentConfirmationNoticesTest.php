<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Actions\RecordManualPaymentAction;
use App\Domains\Finance\Enums\PaymentMethod;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Mail\AdminNewEnrollmentMail;
use App\Mail\EnrollmentConfirmedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * KNOWN_ISSUES #24 / SPEC §41.
 *
 * `RecordManualPaymentAction`'s own docblock makes a promise:
 *
 *   > the payment is created confirmed with provider "manual" and flows
 *   > through the **SAME PaymentConfirmed listeners** as a webhook
 *   > confirmation — **one money→access path for every kind of money**.
 *
 * Access kept that promise; **telling the family did not**. The three
 * enrollment notices — the student's email, the student's SMS, the admin
 * new-enrollment email — were not listeners at all. They were private methods
 * on `PaymentService`, called by hand immediately after it fired
 * `PaymentConfirmed`, which is exactly what §41 forbids: "Enrollment code must
 * not directly call notification implementation classes."
 *
 * So the structural complaint had a user-visible cost, and it landed on the
 * families least likely to be online: **pay by card and you get an email and
 * an SMS; hand over cash at the office and you get silence.** The enrollment
 * activates either way, and nothing tells the parent it did. The admin sees
 * "Manual payment recorded — enrollment updated"; the family sees nothing.
 *
 * Moving the three sends onto the `PaymentConfirmed` event fixes both at once,
 * and this file pins the half that was missing.
 */
function manualPaymentEnrollment(): array
{
    $payer = User::factory()->create(['email' => 'parent'.uniqid().'@example.test']);
    makeStudent(['user_id' => $payer->id, 'first_name' => 'Paying', 'last_name' => 'Student']);
    $student = makeRegistrationStudent(['user_id' => $payer->id]);

    $course = Course::factory()->create([
        'registration_fee_amount' => 250.0,
        'requires_admin_approval' => false,
        'workflow_status' => 'published',
        'status' => 'open',
    ]);

    $enrollment = CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => $student->id,
        'status' => 'pending',
        'payment_status' => 'pending',
        'enrollment_type' => 'paid',
    ]);

    return compact('payer', 'student', 'course', 'enrollment');
}

it('tells the family when an admin records cash, not only when a card clears', function () {
    Mail::fake();
    ['payer' => $payer, 'course' => $course, 'enrollment' => $enrollment] = manualPaymentEnrollment();

    app(RecordManualPaymentAction::class)->execute(
        'course_enrollment',
        $enrollment->id,
        $payer->id,
        250.0,
        'Receipt 4471',
        $payer->id,
        PaymentMethod::Cash->value,
        ['course_id' => $course->id],
    );

    // The enrollment activates — that half always worked.
    expect($enrollment->fresh()->status)->toBe('active');

    // And now the family is told, which is the half that did not.
    // `assertQueued`, not `assertSent`: `EnrollmentConfirmedMail implements
    // ShouldQueue`, so Laravel queues it even though the call site says
    // `->send()`. That is pre-existing and unchanged here — but it means this
    // email has always needed a queue worker, which KNOWN_ISSUES #8 records as
    // an operator gap on this deployment.
    Mail::assertQueued(EnrollmentConfirmedMail::class, function ($mail) use ($payer) {
        return $mail->hasTo($payer->email);
    });
});

it('tells an admin a new enrollment arrived, whichever way the money did', function () {
    Mail::fake();
    config(['mail.admin_notification_address' => 'office@example.test']);

    ['payer' => $payer, 'course' => $course, 'enrollment' => $enrollment] = manualPaymentEnrollment();

    app(RecordManualPaymentAction::class)->execute(
        'course_enrollment',
        $enrollment->id,
        $payer->id,
        250.0,
        null,
        $payer->id,
        PaymentMethod::Cash->value,
        ['course_id' => $course->id],
    );

    Mail::assertQueued(AdminNewEnrollmentMail::class, function ($mail) {
        return $mail->hasTo('office@example.test');
    });
});

it('sends the confirmation SMS through the sender contract, never a mail class', function () {
    Mail::fake();
    ['payer' => $payer, 'course' => $course, 'enrollment' => $enrollment] = manualPaymentEnrollment();

    // The family's number, which is what makes an SMS worth sending at all.
    $payer->contacts()->create(['type' => 'mobile', 'value' => '9607820288']);

    // A spy on the contract rather than on the gateway: the point of §42's
    // interface is that this is the seam, and the live-SMS kill-switch lives
    // behind it.
    $sent = [];
    app()->instance(SmsSenderInterface::class, new class($sent) implements SmsSenderInterface
    {
        public function __construct(public array &$sent) {}

        public function sendSms(string $to, string $message, array $options = []): array
        {
            $this->sent[] = [$to, $message];

            return ['success' => true, 'message_id' => 'spy', 'status' => 'sent'];
        }
    });

    app(RecordManualPaymentAction::class)->execute(
        'course_enrollment',
        $enrollment->id,
        $payer->id,
        250.0,
        null,
        $payer->id,
        PaymentMethod::Cash->value,
        ['course_id' => $course->id],
    );

    expect($sent)->toHaveCount(1)
        ->and($sent[0][0])->toBe('9607820288')
        // The course name comes from `course_id` here, not from `items` — a
        // manual payment has no item rows. Before this it read "… for Paying
        // – . Pending admin approval.", a dangling dash with nothing after it,
        // which nobody saw because manual payments sent no SMS at all.
        ->and($sent[0][1])->toContain($course->title)
        ->and($sent[0][1])->not->toContain(' – .');
});
