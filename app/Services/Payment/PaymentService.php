<?php

namespace App\Services\Payment;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\RegistrationStudent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        protected PaymentProviderInterface $provider
    ) {}

    /**
     * Create consolidated payment for multiple enrollments.
     *
     * @param array<int, array{enrollment: CourseEnrollment, course: Course, amount: float}> $feeEnrollments
     */
    public function createConsolidatedPayment(User $payer, RegistrationStudent $student, array $feeEnrollments): Payment
    {
        $totalAmount = array_sum(array_column($feeEnrollments, 'amount'));
        $first = $feeEnrollments[0];
        $firstCourse = $first['course'];

        $payment = Payment::create([
            'user_id' => $payer->id,
            'student_id' => $student->id,
            'course_id' => $firstCourse->id,
            'amount' => $totalAmount,
            'currency' => $firstCourse->registration_fee_currency ?? 'MVR',
            'status' => 'initiated',
            'provider' => 'bml',
            'merchant_reference' => 'AKURU-' . strtoupper(Str::uuid()->toString()),
        ]);

        foreach ($feeEnrollments as $fe) {
            PaymentItem::create([
                'payment_id' => $payment->id,
                'enrollment_id' => $fe['enrollment']->id,
                'course_id' => $fe['course']->id,
                'amount' => $fe['amount'],
            ]);
        }

        return $payment;
    }

    public function initiatePayment(Payment $payment, array $context = []): PaymentInitiationResult
    {
        return $this->provider->initiate($payment, $context);
    }

    public function handleCallback(Request $request): void
    {
        $result = $this->provider->verifyCallback($request);

        if (!$result->verified) {
            abort(400, 'Invalid callback');
        }

        DB::transaction(function () use ($result) {
            $payment = Payment::where('merchant_reference', $result->merchantReference)->lockForUpdate()->first();

            if (!$payment) {
                return;
            }

            if ($payment->status === 'confirmed') {
                return;
            }

            if (!$result->isPaymentSuccess()) {
                return;
            }

            $payment->update([
                'status' => 'confirmed',
                'provider_reference' => $result->providerReference,
                'callback_payload' => $result->rawPayload,
                'confirmed_at' => now(),
            ]);

            foreach ($payment->items as $item) {
                $enrollment = $item->enrollment;
                $enrollment->update(['payment_status' => 'confirmed']);

                $course = $enrollment->course;
                if (!$course->requires_admin_approval) {
                    $enrollment->update([
                        'status' => 'active',
                        'enrolled_at' => now(),
                    ]);
                }
            }
        });
    }

    public function getPaymentStatus(string $merchantReference): ?array
    {
        $payment = Payment::where('merchant_reference', $merchantReference)
            ->orWhere('local_id', $merchantReference)
            ->first();

        if (! $payment) {
            return null;
        }

        return [
            'status' => $payment->status,
            'confirmed' => $payment->isConfirmed(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'merchant_reference' => $payment->local_id ?? $payment->merchant_reference,
        ];
    }
}
