<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Otp;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\RegistrationStudent;
use App\Models\User;
use App\Models\UserContact;
use App\Services\Payment\BmlPaymentProvider;
use App\Services\Payment\PaymentInitiationResult;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentVerificationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

/**
 * 7 critical registration + payment flow tests.
 */
class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Test 1: StartRegistrationRequest null-safe withValidator
    // -------------------------------------------------------------------------

    public function test_start_registration_does_not_error_on_empty_contact_value(): void
    {
        // contact_value is required by rules, so submitting empty would fail on 'required'
        // but NOT on the custom preg_replace/filter_var logic (which previously crashed on null).
        $response = $this->post(route('courses.register.start'), [
            'contact_type'  => 'mobile',
            'contact_value' => '',
        ]);

        // Should get a validation error for 'required', not a PHP TypeError/500
        $response->assertSessionHasErrors('contact_value');
        $this->assertNotEquals(500, $response->status());
    }

    public function test_start_registration_does_not_error_on_null_contact_type(): void
    {
        $response = $this->post(route('courses.register.start'), [
            'contact_type'  => '',
            'contact_value' => '',
        ]);

        // Required rules fire, no PHP error from preg_replace on null
        $response->assertSessionHasErrors(['contact_type']);
        $this->assertNotEquals(500, $response->status());
    }

    // -------------------------------------------------------------------------
    // Test 2: OTP verify marks used_at and cannot be reused
    // -------------------------------------------------------------------------

    public function test_otp_verify_marks_used_at_and_cannot_be_reused(): void
    {
        $user    = User::factory()->create(['force_password_change' => false]);
        $contact = UserContact::create([
            'user_id'    => $user->id,
            'type'       => 'mobile',
            'value'      => '+9607820001',
            'is_primary' => true,
        ]);

        $code = '123456';
        $otp  = Otp::createForContact($contact, 'verify_contact', $code);

        // First verification succeeds
        $this->withSession([
            'pending_contact_id' => $contact->id,
            'pending_user_id'    => $user->id,
        ]);

        $response = $this->post(route('courses.register.verify'), ['code' => $code]);
        $response->assertRedirect();

        $otp->refresh();
        $this->assertNotNull($otp->used_at, 'used_at should be set after first verify');

        // Second attempt with same code should fail (OTP is now used)
        $this->withSession([
            'pending_contact_id' => $contact->id,
            'pending_user_id'    => $user->id,
        ]);

        $response2 = $this->post(route('courses.register.verify'), ['code' => $code]);
        $response2->assertSessionHasErrors('code');
    }

    // -------------------------------------------------------------------------
    // Test 3: enroll() requires course_ids from request (no session fallback)
    // -------------------------------------------------------------------------

    public function test_enroll_requires_course_ids_from_request(): void
    {
        $user    = User::factory()->create(['force_password_change' => false]);
        $contact = UserContact::create([
            'user_id'     => $user->id,
            'type'        => 'mobile',
            'value'       => '+9607820002',
            'is_primary'  => true,
            'verified_at' => now(),
        ]);
        $course = Course::factory()->create(['registration_fee_amount' => 0]);

        // session has course_ids (old behavior), but request does NOT
        $this->actingAs($user)
            ->withSession(['pending_selected_course_ids' => [$course->id]]);

        $response = $this->post(route('courses.register.enroll'), [
            'flow'       => 'adult',
            'first_name' => 'Ali',
            'last_name'  => 'Mohamed',
            'dob'        => now()->subYears(20)->format('Y-m-d'),
            // course_ids intentionally omitted
        ]);

        // Must get a validation error for course_ids, not silently use session
        $response->assertSessionHasErrors('course_ids');
    }

    public function test_enroll_succeeds_when_course_ids_in_request(): void
    {
        $user    = User::factory()->create(['force_password_change' => false]);
        $contact = UserContact::create([
            'user_id'     => $user->id,
            'type'        => 'mobile',
            'value'       => '+9607820003',
            'is_primary'  => true,
            'verified_at' => now(),
        ]);
        $course = Course::factory()->create([
            'registration_fee_amount' => 0,
            'requires_admin_approval' => false,
        ]);

        $response = $this->actingAs($user)->post(route('courses.register.enroll'), [
            'flow'       => 'adult',
            'course_ids' => [$course->id],
            'first_name' => 'Ali',
            'last_name'  => 'Mohamed',
            'dob'        => now()->subYears(20)->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('courses.register.complete'));
        $this->assertDatabaseHas('course_enrollments', ['course_id' => $course->id]);
    }

    // -------------------------------------------------------------------------
    // Test 4: return endpoint finalizes by ref without session
    // -------------------------------------------------------------------------

    public function test_return_endpoint_finalizes_payment_without_session(): void
    {
        $user    = User::factory()->create();
        $student = RegistrationStudent::create([
            'user_id'    => $user->id,
            'first_name' => 'Fathimath',
            'last_name'  => 'Hassan',
            'dob'        => now()->subYears(22),
        ]);
        $course  = Course::factory()->create(['registration_fee_amount' => 100, 'requires_admin_approval' => false]);

        $enrollment = CourseEnrollment::create([
            'student_id'     => $student->id,
            'course_id'      => $course->id,
            'status'         => 'pending',
            'payment_status' => 'pending',
        ]);

        $payment = Payment::create([
            'user_id'            => $user->id,
            'student_id'         => $student->id,
            'course_id'          => $course->id,
            'amount'             => 100,
            'currency'           => 'MVR',
            'status'             => 'pending',
            'provider'           => 'bml',
            'merchant_reference' => 'AKURU-RETURN-001',
        ]);

        PaymentItem::create([
            'payment_id'    => $payment->id,
            'enrollment_id' => $enrollment->id,
            'course_id'     => $course->id,
            'amount'        => 100,
        ]);
        $enrollment->update(['payment_id' => $payment->id]);

        // Mock provider to return success
        $mockProvider = Mockery::mock(BmlPaymentProvider::class)->makePartial();
        $mockProvider->shouldReceive('queryStatus')
            ->with('AKURU-RETURN-001')
            ->andReturn(new PaymentVerificationResult(
                true, 'AKURU-RETURN-001', 'BML-TXN-RTN', 'completed', [], null, true
            ));
        $this->app->instance(\App\Services\Payment\PaymentProviderInterface::class, $mockProvider);

        // No session – just ?ref= in URL. Use withoutMiddleware to skip locale redirects in test env.
        $response = $this->withoutMiddleware()
            ->get(route('payments.bml.return') . '?ref=AKURU-RETURN-001');

        $response->assertOk();

        $payment->refresh();
        $this->assertSame('confirmed', $payment->status);

        $enrollment->refresh();
        $this->assertSame('active', $enrollment->status);
    }

    // -------------------------------------------------------------------------
    // Test 5: webhook signature uses raw body; invalid signature rejected
    // -------------------------------------------------------------------------

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['bml.webhook_secret' => 'test-secret']);

        $rawBody  = '{"localId":"AKURU-SIG-001","state":"completed"}';
        $badSig   = 'badhash';

        $response = $this->call(
            'POST',
            url('/webhooks/bml'),
            [],
            [],
            [],
            ['HTTP_X-BML-Signature' => $badSig, 'CONTENT_TYPE' => 'application/json'],
            $rawBody
        );

        $response->assertStatus(400);
    }

    public function test_webhook_accepts_valid_raw_body_signature(): void
    {
        $secret  = 'test-secret-valid';
        $rawBody = '{"localId":"AKURU-RAWSIG-001","state":"completed"}';
        $sig     = hash_hmac('sha256', $rawBody, $secret);

        config(['bml.webhook_secret' => $secret]);

        $user    = User::factory()->create();
        $student = RegistrationStudent::create([
            'user_id'    => $user->id,
            'first_name' => 'Test',
            'last_name'  => 'User',
            'dob'        => now()->subYears(22),
        ]);
        $course = Course::factory()->create(['requires_admin_approval' => false]);

        $enrollment = CourseEnrollment::create([
            'student_id'     => $student->id,
            'course_id'      => $course->id,
            'status'         => 'pending',
            'payment_status' => 'pending',
        ]);

        $payment = Payment::create([
            'user_id'            => $user->id,
            'student_id'         => $student->id,
            'course_id'          => $course->id,
            'amount'             => 100,
            'currency'           => 'MVR',
            'status'             => 'pending',
            'provider'           => 'bml',
            'merchant_reference' => 'AKURU-RAWSIG-001',
        ]);

        PaymentItem::create([
            'payment_id'    => $payment->id,
            'enrollment_id' => $enrollment->id,
            'course_id'     => $course->id,
            'amount'        => 100,
        ]);
        $enrollment->update(['payment_id' => $payment->id]);

        // Mock queryStatus so PaymentService.finalizeByReference() confirms
        $mockProvider = Mockery::mock(BmlPaymentProvider::class)->makePartial();
        $mockProvider->shouldReceive('queryStatus')
            ->andReturn(new PaymentVerificationResult(
                true, 'AKURU-RAWSIG-001', 'BML-TXN-RAWSIG', 'completed', [], null, true
            ));
        $mockProvider->shouldReceive('verifyCallback')
            ->andReturnUsing(function ($request) {
                $rawBody = $request->getContent();
                $secret  = config('bml.webhook_secret');
                $sig     = $request->header('X-BML-Signature');
                $valid   = hash_equals(hash_hmac('sha256', $rawBody, $secret), $sig);
                if (! $valid) {
                    return new PaymentVerificationResult(false, null, null, null, [], 'Invalid signature');
                }
                $payload = json_decode($rawBody, true);
                return new PaymentVerificationResult(true, $payload['localId'], null, $payload['state'], $payload, null, true);
            });
        $this->app->instance(\App\Services\Payment\PaymentProviderInterface::class, $mockProvider);

        $response = $this->call(
            'POST',
            url('/webhooks/bml'),
            [],
            [],
            [],
            ['HTTP_X-BML-Signature' => $sig, 'CONTENT_TYPE' => 'application/json'],
            $rawBody
        );

        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // Test 6: enrollment unique constraint prevents duplicates
    // -------------------------------------------------------------------------

    public function test_enrollment_unique_prevents_duplicates(): void
    {
        $user    = User::factory()->create(['force_password_change' => false]);
        $contact = UserContact::create([
            'user_id'     => $user->id,
            'type'        => 'mobile',
            'value'       => '+9607820099',
            'is_primary'  => true,
            'verified_at' => now(),
        ]);
        $course = Course::factory()->create([
            'registration_fee_amount' => 0,
            'requires_admin_approval' => false,
        ]);

        $enrollData = [
            'flow'       => 'adult',
            'course_ids' => [$course->id],
            'first_name' => 'Hussain',
            'last_name'  => 'Rasheed',
            'dob'        => now()->subYears(25)->format('Y-m-d'),
        ];

        // First enroll succeeds
        $this->actingAs($user)->post(route('courses.register.enroll'), $enrollData);

        // Second enroll with same course – should not create a duplicate
        $this->actingAs($user)->post(route('courses.register.enroll'), $enrollData);

        $count = CourseEnrollment::where('course_id', $course->id)->count();
        $this->assertLessThanOrEqual(1, $count, 'Should not create duplicate enrollments');
    }

    // -------------------------------------------------------------------------
    // Test 7: finalizeByReference is idempotent
    // -------------------------------------------------------------------------

    public function test_finalize_by_reference_is_idempotent(): void
    {
        $user    = User::factory()->create();
        $student = RegistrationStudent::create([
            'user_id'    => $user->id,
            'first_name' => 'Ibrahim',
            'last_name'  => 'Ali',
            'dob'        => now()->subYears(30),
        ]);
        $course = Course::factory()->create(['requires_admin_approval' => false]);

        $enrollment = CourseEnrollment::create([
            'student_id'     => $student->id,
            'course_id'      => $course->id,
            'status'         => 'active',
            'payment_status' => 'confirmed',
            'enrolled_at'    => now()->subMinutes(5),
        ]);

        $confirmedAt = now()->subMinutes(5);
        $payment = Payment::create([
            'user_id'            => $user->id,
            'student_id'         => $student->id,
            'course_id'          => $course->id,
            'amount'             => 200,
            'currency'           => 'MVR',
            'status'             => 'confirmed',
            'provider'           => 'bml',
            'merchant_reference' => 'AKURU-IDEM-FINAL-001',
            'paid_at'            => $confirmedAt,
            'confirmed_at'       => $confirmedAt,
        ]);

        PaymentItem::create([
            'payment_id'    => $payment->id,
            'enrollment_id' => $enrollment->id,
            'course_id'     => $course->id,
            'amount'        => 200,
        ]);

        $mockProvider = Mockery::mock(BmlPaymentProvider::class)->makePartial();
        $mockProvider->shouldNotReceive('queryStatus'); // should short-circuit
        $this->app->instance(\App\Services\Payment\PaymentProviderInterface::class, $mockProvider);

        /** @var PaymentService $service */
        $service = $this->app->make(PaymentService::class);

        $result1 = $service->finalizeByReference('AKURU-IDEM-FINAL-001');
        $result2 = $service->finalizeByReference('AKURU-IDEM-FINAL-001');

        $this->assertSame('confirmed', $result1->status);
        $this->assertSame('confirmed', $result2->status);
        // enrolled_at must not have changed
        $enrollment->refresh();
        $this->assertTrue($enrollment->enrolled_at->lessThanOrEqualTo(now()->subSeconds(4)));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
