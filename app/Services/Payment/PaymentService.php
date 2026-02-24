<?php

namespace App\Services\Payment;

use App\Mail\EnrollmentConfirmedMail;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\RegistrationStudent;
use App\Models\User;
use App\Services\SmsGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Services\Payment\PaymentVerificationResult;

class PaymentService
{
    /** Payment statuses considered final (no further state changes expected). */
    private const FINAL_STATUSES = ['confirmed', 'failed', 'cancelled', 'expired', 'paid'];

    public function __construct(
        protected PaymentProviderInterface $provider,
        protected SmsGatewayService        $sms,
    ) {}

    /**
     * Create consolidated payment for multiple enrollments.
     *
     * @param array<int, array{enrollment: CourseEnrollment, course: Course, amount: float}> $feeEnrollments
     */
    /**
     * Create a Payment record for a paid course enrollment WITHOUT creating any
     * RegistrationStudent or CourseEnrollment rows yet.  The full enrollment data
     * is stored in enrollment_pending_payload and is written to the DB only once
     * BML confirms the payment (see EnrollmentService::createEnrollmentForConfirmedPayment).
     *
     * @param \Illuminate\Support\Collection $courses
     */
    public function createPaymentForPendingEnrollment(User $payer, array $enrollmentPayload, $courses): Payment
    {
        $totalAmount = collect($courses)->sum(fn ($c) => (float) ($c->registration_fee_amount ?? $c->fee ?? 0));
        $totalLaar   = (int) round($totalAmount * 100);
        $firstCourse = collect($courses)->first();

        return Payment::create([
            'user_id'                    => $payer->id,
            'student_id'                 => null,
            'course_id'                  => $firstCourse->id,
            'amount'                     => $totalAmount,
            'amount_laar'                => $totalLaar,
            'currency'                   => config('bml.default_currency') ?: ($firstCourse->registration_fee_currency ?? 'MVR'),
            'status'                     => 'initiated',
            'provider'                   => 'bml',
            'merchant_reference'         => 'AKURU-' . strtoupper(Str::uuid()->toString()),
            'enrollment_pending_payload' => $enrollmentPayload,
        ]);
    }

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
            'currency'           => config('bml.default_currency') ?: ($firstCourse->registration_fee_currency ?? 'MVR'),
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

            // Query provider by BML's own transaction ID (preferred) or fall back to merchant_reference.
            // BML knows transactions by their internal ID, not our merchant_reference.
            $queryRef = $payment->bml_transaction_id ?? $payment->merchant_reference;
            $result = $this->provider->queryStatus($queryRef);

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

                // Deferred-enrollment flow
                if ($payment->enrollment_pending_payload) {
                    $this->finalizeDeferredEnrollment($payment->fresh());
                } else {
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
                }

                $this->sendConfirmationEmail($payment->fresh());
                $this->notifyAdminsPaymentConfirmed($payment);
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

                // Deferred-enrollment flow: create student + enrollment records now
                if ($payment->enrollment_pending_payload) {
                    $this->finalizeDeferredEnrollment($payment->fresh());
                } else {
                    // Legacy / existing-enrollment flow: just activate the linked enrollments
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
                }

                $this->sendConfirmationEmail($payment->fresh());
                $this->notifyAdminsPaymentConfirmed($payment);
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

    /**
     * Resolve EnrollmentService lazily (avoids circular DI) and create the deferred enrollment.
     */
    private function finalizeDeferredEnrollment(Payment $payment): void
    {
        try {
            app(\App\Services\Enrollment\EnrollmentService::class)
                ->createEnrollmentForConfirmedPayment($payment);
        } catch (\Throwable $e) {
            Log::error('PaymentService: deferred enrollment creation failed', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch enrollment confirmation notifications (email + SMS).
     * Both are silently skipped if no contact is on file.
     */
    private function sendConfirmationEmail(Payment $payment): void
    {
        $payment->loadMissing(['user', 'items.course', 'items.enrollment', 'student']);
        $this->sendConfirmationEmailNotification($payment);
        $this->sendConfirmationSms($payment);
        $this->sendAdminNewEnrollmentNotification($payment);
    }

    private function sendAdminNewEnrollmentNotification(Payment $payment): void
    {
        $adminEmail = config('mail.admin_notification_address')
            ?? config('mail.from.address');

        if (! $adminEmail) {
            return;
        }

        try {
            Mail::to($adminEmail)->queue(new \App\Mail\AdminNewEnrollmentMail($payment));
        } catch (\Throwable $e) {
            Log::warning('AdminNewEnrollmentMail: failed to queue', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function sendConfirmationEmailNotification(Payment $payment): void
    {
        try {
            $user = $payment->user;
            if (! $user) {
                return;
            }
            $emailContact = $user->contacts()->where('type', 'email')->whereNotNull('verified_at')->first();
            $toAddress    = $emailContact?->value ?? $user->email ?? null;
            // Also try unverified email contacts (user may not have verified yet)
            if (! $toAddress) {
                $toAddress = $user->contacts()->where('type', 'email')->first()?->value;
            }
            if (! $toAddress) {
                return;
            }
            Mail::to($toAddress)->send(new EnrollmentConfirmedMail($payment));
        } catch (\Throwable $e) {
            Log::warning('EnrollmentConfirmedMail: failed to send', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function sendConfirmationSms(Payment $payment): void
    {
        try {
            $user = $payment->user;
            if (! $user) {
                return;
            }
            $mobileContact = $user->contacts()->where('type', 'mobile')->first();
            if (! $mobileContact?->value) {
                return;
            }

            $student    = $payment->student;
            $studentName = $student?->first_name ?? $user->name ?? 'Student';
            $courses    = $payment->items->map(fn ($i) => $i->course?->title)->filter()->implode(', ');
            $ref        = $payment->local_id ?? $payment->merchant_reference;

            $message = "Akuru: Payment received for {$studentName} – {$courses}. Pending admin approval.";

            $this->sms->sendSms($mobileContact->value, $message);
        } catch (\Throwable $e) {
            Log::warning('EnrollmentConfirmedSms: failed to send', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function notifyAdminsPaymentConfirmed(Payment $payment): void
    {
        try {
            $payment->loadMissing(['user', 'items.enrollment.course']);
            $payer = $payment->user;
            $payerName = $payer?->name ?? 'Unknown';

            $admins = \App\Models\User::role(['super_admin', 'admin'])->get();

            foreach ($payment->items as $item) {
                $enrollment = $item->enrollment;
                if (! $enrollment) {
                    continue;
                }
                $enrollment->loadMissing('course');
                $courseName = $enrollment->course?->title ?? 'Unknown';
                $amount     = number_format((float) ($payment->amount ?? 0), 2);
                $currency   = $payment->currency ?? 'MVR';
                $message    = "[Akuru] Payment confirmed: {$payerName} → {$courseName} ({$currency} {$amount})";

                foreach ($admins as $admin) {
                    $adminMobile = $admin->contacts()->where('type', 'mobile')->value('value')
                        ?? $admin->phone
                        ?? null;
                    if ($adminMobile) {
                        $this->sms->sendSms($adminMobile, $message);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('notifyAdminsPaymentConfirmed: failed', [
                'payment_id' => $payment->id,
                'error'      => $e->getMessage(),
            ]);
        }
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
