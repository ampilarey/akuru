<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\RegistrationStudent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BmlWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_paid_marks_payment_paid_and_enrollment_enrolled(): void
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
            'merchant_reference' => 'AKURU-WEBHOOK-001',
            'local_id' => 'AKURU-WEBHOOK-001',
            'bml_transaction_id' => 'BML-TXN-001',
        ]);

        PaymentItem::create([
            'payment_id' => $payment->id,
            'enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'amount' => 100,
        ]);
        $enrollment->update(['payment_id' => $payment->id]);

        $response = $this->postJson(url('/webhooks/bml'), [
            'reference' => 'AKURU-WEBHOOK-001',
            'transactionId' => 'BML-TXN-001',
            'status' => 'completed',
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $this->assertSame('confirmed', $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($payment->webhook_payload);

        $enrollment->refresh();
        $this->assertSame('confirmed', $enrollment->payment_status);
        $this->assertSame('active', $enrollment->status);
        $this->assertNotNull($enrollment->enrolled_at);
    }

    public function test_webhook_idempotent_does_not_double_enroll(): void
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
            'status' => 'active',
            'payment_status' => 'confirmed',
            'enrolled_at' => now(),
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => 50,
            'currency' => 'MVR',
            'status' => 'confirmed',
            'provider' => 'bml',
            'merchant_reference' => 'AKURU-IDEM-002',
            'local_id' => 'AKURU-IDEM-002',
            'bml_transaction_id' => 'BML-TXN-002',
            'paid_at' => now(),
        ]);

        PaymentItem::create([
            'payment_id' => $payment->id,
            'enrollment_id' => $enrollment->id,
            'course_id' => $course->id,
            'amount' => 50,
        ]);

        $response1 = $this->postJson(url('/webhooks/bml'), [
            'reference' => 'AKURU-IDEM-002',
            'transactionId' => 'BML-TXN-002',
            'status' => 'completed',
        ]);
        $response2 = $this->postJson(url('/webhooks/bml'), [
            'reference' => 'AKURU-IDEM-002',
            'transactionId' => 'BML-TXN-002',
            'status' => 'completed',
        ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        $payment->refresh();
        $this->assertSame('confirmed', $payment->status);

        $count = CourseEnrollment::where('student_id', $student->id)->where('course_id', $course->id)->count();
        $this->assertSame(1, $count);
    }
}
