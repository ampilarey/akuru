<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\RegistrationStudent;
use App\Models\User;
use App\Models\UserContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_confirms_payment_and_updates_enrollment(): void
    {
        $user = User::factory()->create();
        $student = RegistrationStudent::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'dob' => now()->subYears(25),
        ]);
        $course = Course::factory()->create([
            'registration_fee_amount' => 100,
            'requires_admin_approval' => false,
        ]);

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
            'amount' => 100,
            'currency' => 'MVR',
            'status' => 'pending',
            'provider' => 'bml',
            'merchant_reference' => 'AKURU-TEST-123',
        ]);

        PaymentItem::create([
            'payment_id' => $payment->id,
            'enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'amount' => 100,
        ]);

        $enrollment->update(['payment_id' => $payment->id]);

        $response = $this->postJson(route('payments.bml.callback'), [
            'reference' => 'AKURU-TEST-123',
            'status' => 'completed',
            'transactionId' => 'BML-REF-456',
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertEquals('confirmed', $payment->status);
        $this->assertNotNull($payment->confirmed_at);

        $enrollment->refresh();
        $this->assertEquals('confirmed', $enrollment->payment_status);
        $this->assertEquals('active', $enrollment->status);
        $this->assertNotNull($enrollment->enrolled_at);
    }

    public function test_callback_idempotent_does_not_double_confirm(): void
    {
        $user = User::factory()->create();
        $student = RegistrationStudent::create([
            'user_id' => $user->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'dob' => now()->subYears(25),
        ]);
        $course = Course::factory()->create([
            'registration_fee_amount' => 50,
            'requires_admin_approval' => false,
        ]);

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
            'amount' => 50,
            'currency' => 'MVR',
            'status' => 'confirmed',
            'provider' => 'bml',
            'merchant_reference' => 'AKURU-IDEM-789',
            'confirmed_at' => now(),
        ]);

        PaymentItem::create([
            'payment_id' => $payment->id,
            'enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'amount' => 50,
        ]);

        $enrollment->update(['payment_id' => $payment->id, 'payment_status' => 'confirmed']);

        $response1 = $this->postJson(route('payments.bml.callback'), [
            'reference' => 'AKURU-IDEM-789',
            'status' => 'completed',
            'transactionId' => 'BML-REF-999',
        ]);

        $response2 = $this->postJson(route('payments.bml.callback'), [
            'reference' => 'AKURU-IDEM-789',
            'status' => 'completed',
            'transactionId' => 'BML-REF-999',
        ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $payment->refresh();
        $this->assertEquals('confirmed', $payment->status);
    }
}
