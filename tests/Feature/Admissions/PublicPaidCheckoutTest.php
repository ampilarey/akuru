<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\PaymentItem;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use App\Domains\Identity\Models\Otp;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\People\Models\RegistrationStudent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fakeLegacyBmlProvider(): void
{
    app()->instance(PaymentProviderInterface::class, new class implements PaymentProviderInterface
    {
        public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
        {
            return new PaymentInitiationResult(true, 'https://bml.test/pay/legacy');
        }

        public function verifyCallback(\Illuminate\Http\Request $request): PaymentVerificationResult
        {
            return new PaymentVerificationResult(
                verified: true,
                merchantReference: (string) $request->input('reference'),
                providerReference: 'BML-P42',
                status: 'completed',
                rawPayload: $request->all(),
                isConfirmed: true,
            );
        }

        public function queryStatus(string $merchantReference): ?PaymentVerificationResult
        {
            return new PaymentVerificationResult(
                verified: true,
                merchantReference: $merchantReference,
                providerReference: 'BML-P42',
                status: 'completed',
                rawPayload: [],
                isConfirmed: true,
            );
        }
    });
}

function makeVerifiedCheckoutUser(): array
{
    $user = User::factory()->create(['force_password_change' => false]);
    $contact = UserContact::create([
        'user_id' => $user->id,
        'type' => 'mobile',
        'value' => '7'.random_int(100000, 999999),
        'is_primary' => true,
        'verified_at' => now(),
    ]);

    return [$user, $contact];
}

it('enrolls first for a paid public checkout and activates on the webhook', function () {
    [$user, $contact] = makeVerifiedCheckoutUser();
    $paid = Course::factory()->create(['registration_fee_amount' => 150, 'requires_admin_approval' => false]);
    $free = Course::factory()->create(['registration_fee_amount' => 0, 'requires_admin_approval' => false]);
    fakeLegacyBmlProvider();

    $this->actingAs($user)->post(route('courses.register.enroll'), [
        'flow' => 'adult',
        'course_ids' => [$paid->id, $free->id],
        'first_name' => 'Aishath',
        'last_name' => 'Naeema',
        'dob' => now()->subYears(24)->format('Y-m-d'),
        'gender' => 'female',
        'id_type' => 'national_id',
        'national_id' => 'A445566',
    ])->assertRedirect(route('courses.register.enroll.otp'));

    Otp::createForContact($contact, 'login', '654321');

    $this->actingAs($user)->post(route('courses.register.enroll.confirm'), [
        'otp_code' => '654321',
        'terms_accepted' => '1',
    ])->assertRedirect('https://bml.test/pay/legacy');

    // Enroll-first: rows exist BEFORE any money moves — the webhook only
    // activates, it never creates (no enrollment_pending_payload written).
    $paidEnrollment = CourseEnrollment::where('course_id', $paid->id)->firstOrFail();
    $freeEnrollment = CourseEnrollment::where('course_id', $free->id)->firstOrFail();
    $payment = Payment::query()->firstOrFail();
    expect($paidEnrollment->status)->toBe('pending')
        ->and($paidEnrollment->payment_status)->toBe('pending')
        ->and((int) $paidEnrollment->payment_id)->toBe($payment->id)
        ->and($freeEnrollment->payment_status)->toBe('not_required')
        ->and((string) $payment->amount)->toBe('150.00')
        ->and($payment->enrollment_pending_payload)->toBeNull()
        ->and(PaymentItem::where('payment_id', $payment->id)->count())->toBe(1);

    $this->postJson(url('/webhooks/bml'), [
        'reference' => $payment->merchant_reference,
        'transactionId' => 'BML-P42',
        'status' => 'completed',
    ])->assertStatus(200);

    $paidEnrollment->refresh();
    expect($paidEnrollment->payment_status)->toBe('confirmed')
        ->and($paidEnrollment->status)->toBe('active')
        ->and($paidEnrollment->enrolled_at)->not->toBeNull()
        ->and($payment->fresh()->status)->toBe('confirmed');
});

it('reconciles a stuck payment through the same single confirmation path', function () {
    [$user] = makeVerifiedCheckoutUser();
    $student = RegistrationStudent::create([
        'user_id' => $user->id,
        'first_name' => 'Hawwa',
        'last_name' => 'Zahira',
        'dob' => now()->subYears(30),
    ]);
    $course = Course::factory()->create(['registration_fee_amount' => 80, 'requires_admin_approval' => false]);
    $enrollment = CourseEnrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'pending',
        'payment_status' => 'pending',
    ]);
    $payment = Payment::create([
        'user_id' => $user->id,
        'student_id' => $student->id,
        'course_id' => $course->id,
        'amount' => 80,
        'currency' => 'MVR',
        'status' => 'pending',
        'provider' => 'bml',
        'merchant_reference' => 'AKURU-RECONCILE-P42',
        'bml_transaction_id' => 'BML-TXN-P42',
    ]);
    PaymentItem::create([
        'payment_id' => $payment->id,
        'enrollment_id' => $enrollment->id,
        'course_id' => $course->id,
        'amount' => 80,
    ]);
    $enrollment->update(['payment_id' => $payment->id]);
    $payment->timestamps = false;
    $payment->forceFill(['created_at' => now()->subMinutes(20), 'updated_at' => now()->subMinutes(20)])->save();
    fakeLegacyBmlProvider();

    $this->artisan('payments:reconcile')->assertExitCode(0);

    $enrollment->refresh();
    expect($payment->fresh()->status)->toBe('confirmed')
        ->and($enrollment->payment_status)->toBe('confirmed')
        ->and($enrollment->status)->toBe('active')
        ->and($enrollment->enrolled_at)->not->toBeNull();
});

it('still finalizes a pre-P4.2 deferred-payload payment as a legacy-data safety net', function () {
    [$user] = makeVerifiedCheckoutUser();
    $course = Course::factory()->create(['registration_fee_amount' => 100, 'requires_admin_approval' => false]);
    $payment = Payment::create([
        'user_id' => $user->id,
        'course_id' => $course->id,
        'amount' => 100,
        'currency' => 'MVR',
        'status' => 'pending',
        'provider' => 'bml',
        'merchant_reference' => 'AKURU-LEGACY-PAYLOAD-P42',
        'enrollment_pending_payload' => [
            'user_id' => $user->id,
            'flow' => 'adult',
            'student_data' => [
                'first_name' => 'Ismail',
                'last_name' => 'Waheed',
                'dob' => now()->subYears(28)->format('Y-m-d'),
                'gender' => 'male',
                'id_type' => 'national_id',
                'national_id' => 'A778899',
            ],
            'course_ids' => [$course->id],
            'term_id' => null,
            'student_mode' => 'new',
            'child_password' => null,
        ],
    ]);
    fakeLegacyBmlProvider();

    $this->postJson(url('/webhooks/bml'), [
        'reference' => $payment->merchant_reference,
        'transactionId' => 'BML-P42-LEGACY',
        'status' => 'completed',
    ])->assertStatus(200);

    $enrollment = CourseEnrollment::where('course_id', $course->id)->firstOrFail();
    expect($enrollment->payment_status)->toBe('confirmed')
        ->and($enrollment->status)->toBe('active')
        ->and($payment->fresh()->enrollment_pending_payload)->toBeNull()
        ->and(RegistrationStudent::where('user_id', $user->id)->exists())->toBeTrue();
});
