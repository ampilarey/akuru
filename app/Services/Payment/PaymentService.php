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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\Payment\PaymentVerificationResult;

class PaymentService
{
    /** Payment statuses considered final (no further state changes expected). */
    private const FINAL_STATUSES = ['confirmed', 'failed', 'cancelled', 'expired', 'paid'];

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
        $first       = $feeEnrollments[0];
        $firstCourse = $first['course'];

        // Store total as laari (integer) AND legacy decimal for backward compat.
        $totalLaar = (int) round($totalAmount * 100);

        $payment = Payment::create([
            'user_id'            => $payer->id,
            'student_id'         => $student->id,
            'course_id'          => $firstCourse->id,
            'amount'             => $totalAmount,
            'amount_laar'        => $totalLaar,
            'currency'           => $firstCourse->registration_fee_currency ?? 'MVR',
            'status'             => 'initiated',
            'provider'           => 'bml',
            'merchant_reference' => 'AKURU-' . strtoupper(Str::uuid()->toString()),
        ]);

        foreach ($feeEnrollments as $fe) {
            PaymentItem::create([
                'payment_id'   => $payment->id,
                'enrollment_id' => $fe['enrollment']->id,
                'course_id'    => $fe['course']->id,
                'amount'       => $fe['amount'],
            ]);
        }

        return $payment;
    }

    public function initiatePayment(Payment $payment, array $context = []): PaymentInitiationResult
    {
        return $this->provider->initiate($payment, $context);
    }

    /**
     * Idempotent payment finalization by merchant reference.
     *
     * - Locks the payment row FOR UPDATE.
     * - If already in a final state, returns immediately (idempotent).
     * - Queries provider for latest status.
     * - Updates payment + linked enrollments.
     */
    public function finalizeByReference(string $ref): ?Payment
    {
        return DB::transaction(function () use ($ref) {
            /** @var Payment|null $payment */
            $payment = Payment::where('merchant_reference', $ref)
                ->orWhere('local_id', $ref)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                Log::warning('PaymentService::finalizeByReference – payment not found', ['ref' => $ref]);
                return null;
            }

            // Already finalized – return without side effects
            if (in_array($payment->status, self::FINAL_STATUSES, true)) {
                return $payment;
            }

            // Query provider for authoritative status
            $result = $this->provider->queryStatus($payment->merchant_reference);

            if (! $result) {
                Log::info('PaymentService::finalizeByReference – provider query returned null', [
                    'ref' => $ref, 'payment_id' => $payment->id,
                ]);
                return $payment;
            }

            $providerStatus = strtolower((string) ($result->status ?? ''));
            $isSuccess = $result->isPaymentSuccess();

            if ($isSuccess) {
                $payment->update([
                    'status'             => 'confirmed',
                    'provider_reference' => $result->providerReference ?? $payment->provider_reference,
                    'confirmed_at'       => $payment->confirmed_at ?? now(),
                    'paid_at'            => $payment->paid_at ?? now(),
                ]);

                foreach ($payment->items as $item) {
                    $enrollment = $item->enrollment;
                    if (! $enrollment) {
                        continue;
                    }
                    $enrollment->update(['payment_status' => 'confirmed']);
                    $course = $enrollment->course;
                    if (! ($course->requires_admin_approval ?? false)) {
                        $enrollment->update([
                            'status'      => 'active',
                            'enrolled_at' => $enrollment->enrolled_at ?? now(),
                        ]);
                    }
                }
            } elseif (in_array($providerStatus, ['failed', 'cancelled', 'declined'], true)) {
                $payment->update([
                    'status'    => 'failed',
                    'failed_at' => now(),
                ]);
                foreach ($payment->items as $item) {
                    $item->enrollment?->update(['payment_status' => 'required']);
                }
            } elseif (in_array($providerStatus, ['expired'], true)) {
                $payment->update(['status' => 'expired']);
            }
            // status = pending/initiated => leave unchanged, will be retried by webhook

            return $payment->fresh();
        });
    }

    public function handleCallback(Request $request): void
    {
        $result = $this->provider->verifyCallback($request);

        if (! $result->verified) {
            abort(400, 'Invalid callback');
        }

        if (! $result->merchantReference) {
            return;
        }

        // Apply the webhook payload directly (authoritative since signature was verified).
        // This avoids an extra queryStatus() round-trip for webhook confirmations.
        $this->applyVerifiedResult($result->merchantReference, $result);
    }

    /**
     * Apply a verified payment result (from webhook or direct provider response) to a payment.
     * Idempotent: skips if payment is already in a final state.
     */
    private function applyVerifiedResult(string $ref, PaymentVerificationResult $result): void
    {
        DB::transaction(function () use ($ref, $result) {
            $payment = Payment::where('merchant_reference', $ref)
                ->orWhere('local_id', $ref)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                return;
            }

            if (in_array($payment->status, self::FINAL_STATUSES, true)) {
                return; // idempotent
            }

            $webhookPayload = is_array($result->rawPayload) ? $result->rawPayload : [];

            if ($result->isPaymentSuccess()) {
                $payment->update([
                    'status'             => 'confirmed',
                    'provider_reference' => $result->providerReference ?? $payment->provider_reference,
                    'webhook_payload'    => $webhookPayload,
                    'confirmed_at'       => $payment->confirmed_at ?? now(),
                    'paid_at'            => $payment->paid_at ?? now(),
                ]);

                foreach ($payment->items as $item) {
                    $enrollment = $item->enrollment;
                    if (! $enrollment) {
                        continue;
                    }
                    $enrollment->update(['payment_status' => 'confirmed']);
                    $course = $enrollment->course;
                    if (! ($course->requires_admin_approval ?? false)) {
                        $enrollment->update([
                            'status'      => 'active',
                            'enrolled_at' => $enrollment->enrolled_at ?? now(),
                        ]);
                    }
                }
            } else {
                $providerStatus = strtolower((string) ($result->status ?? ''));
                if (in_array($providerStatus, ['failed', 'cancelled', 'declined'], true)) {
                    $payment->update(['status' => 'failed', 'webhook_payload' => $webhookPayload, 'failed_at' => now()]);
                    foreach ($payment->items as $item) {
                        $item->enrollment?->update(['payment_status' => 'required']);
                    }
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
            'status'             => $payment->status,
            'confirmed'          => $payment->isConfirmed(),
            'paid_at'            => $payment->paid_at?->toIso8601String(),
            'merchant_reference' => $payment->local_id ?? $payment->merchant_reference,
        ];
    }
}
