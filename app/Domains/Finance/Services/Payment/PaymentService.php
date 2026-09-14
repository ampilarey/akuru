<?php

namespace App\Domains\Finance\Services\Payment;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Actions\RecordInvoiceReceiptAction;
use App\Domains\Finance\Contracts\PaymentProviderInterface;
use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\PaymentItem;
use App\Domains\Identity\Models\User;
use App\Domains\Website\Actions\RecordFunnelEventAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    /** Payment statuses considered final (no further state changes expected). */
    private const FINAL_STATUSES = ['confirmed', 'failed', 'cancelled', 'expired', 'paid'];

    // No SMS sender any more: sending is a listener's job (§41), so this
    // service no longer knows any notification channel exists.
    public function __construct(
        protected PaymentProviderInterface $provider,
    ) {}

    /**
     * Create consolidated payment for multiple enrollments.
     *
     * P4.2: this is the ONLY payment shape the public checkout creates —
     * enrollments exist (pending) before any money moves, and the webhook
     * activates them through the PaymentConfirmed listener.
     *
     * @param  array<int, array{enrollment: CourseEnrollment, course: Course, amount: float}>  $feeEnrollments
     */
    public function createConsolidatedPayment(User $payer, int $registrationStudentId, array $feeEnrollments): Payment
    {
        $totalAmount = array_sum(array_column($feeEnrollments, 'amount'));
        $first = $feeEnrollments[0];
        $firstCourse = $first['course'];

        // Store total as laari (integer) AND legacy decimal for backward compat.
        $totalLaar = (int) round($totalAmount * 100);

        $payment = Payment::create([
            'user_id' => $payer->id,
            'student_id' => $registrationStudentId,
            'course_id' => $firstCourse->id,
            'amount' => $totalAmount,
            'amount_laar' => $totalLaar,
            'currency' => config('bml.default_currency') ?: ($firstCourse->registration_fee_currency ?? 'MVR'),
            'status' => 'initiated',
            'provider' => 'bml',
            'merchant_reference' => 'AKURU-'.strtoupper(Str::uuid()->toString()),
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

    /**
     * Idempotent payment finalization by merchant reference.
     *
     * - Locks the payment row FOR UPDATE.
     * - If already in a final state, returns immediately (idempotent).
     * - Queries provider for latest status.
     * - Updates the payment; enrollment activation and access grants run in
     *   the PaymentConfirmed listeners (P4.2: the single money→access path).
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

            // **Does this result describe this payment?**
            //
            // `$queryRef` above is `bml_transaction_id`, which can be set from
            // BML's redirect query string — a public, unauthenticated GET.
            // Before this check, an abandoned pending payment could be
            // confirmed by replaying the transaction id of any completed BML
            // transaction, including one of the payer's own earlier purchases:
            // buy one cheap thing, then confirm everything afterwards for free.
            //
            // Rule 12 says access depends on webhook confirmation and never on
            // the return URL. The return URL was not setting the status
            // directly, but it was choosing which transaction the server-side
            // check looked at, which is the same thing with one step in it.
            if (! $this->resultDescribes($payment, $result)) {
                Log::warning('PaymentService::finalizeByReference – provider result is for another payment, refusing', [
                    'payment_id' => $payment->id,
                    'expected' => $payment->merchant_reference,
                    'queried' => $queryRef,
                    'returned' => $result->merchantReference,
                ]);

                return $payment;
            }

            $providerStatus = strtolower((string) ($result->status ?? ''));
            $isSuccess = $result->isPaymentSuccess();

            if ($isSuccess) {
                $payment->update([
                    'status' => 'confirmed',
                    'provider_reference' => $result->providerReference ?? $payment->provider_reference,
                    'confirmed_at' => $payment->confirmed_at ?? now(),
                    'paid_at' => $payment->paid_at ?? now(),
                ]);

                // Legacy-data safety net: payments created before P4.2 carry the
                // enrollment in enrollment_pending_payload. No code writes this
                // payload any more — delete this branch in the cleanup deploy
                // once no non-final payment holds one.
                if ($payment->enrollment_pending_payload) {
                    $this->finalizeDeferredEnrollment($payment->fresh());
                }

                // P4.2: money→access happens HERE — domains activate
                // enrollments / grant access by listening, inside this
                // transaction (a failed listener rolls back with the payment).
                //
                // §41: the confirmation notices are listeners on this same
                // event now (`SendPaymentConfirmationNotices`), deferred to
                // after commit, rather than four hand-calls on the next line.
                // That is why an admin recording cash now reaches the family:
                // `RecordManualPaymentAction` fires this event too.
                event(new PaymentConfirmed($payment->fresh()));

                app(RecordInvoiceReceiptAction::class)->fromConfirmedPayment($payment->fresh());
                $this->recordPaymentCompletedFunnel($payment->fresh());
            } elseif (in_array($providerStatus, ['failed', 'cancelled', 'declined'], true)) {
                $payment->update([
                    'status' => 'failed',
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

    /**
     * Is a provider result about this payment, and not another one?
     *
     * Compared against both `merchant_reference` and `local_id`, because
     * `finalizeByReference` accepts either as the lookup key and BML's payload
     * carries whichever we sent at initiation.
     *
     * A result that names **no** merchant reference is refused. That is the
     * cautious direction and it is also the shape of the bug this closes: the
     * field used to be whatever we passed in, so "missing" and "matching" were
     * indistinguishable. A provider that genuinely cannot tell us which
     * payment it is answering about cannot be allowed to confirm one; the
     * signed webhook remains the path that grants access.
     */
    private function resultDescribes(Payment $payment, PaymentVerificationResult $result): bool
    {
        $returned = trim((string) $result->merchantReference);

        if ($returned === '') {
            return false;
        }

        return hash_equals((string) $payment->merchant_reference, $returned)
            || hash_equals((string) $payment->local_id, $returned);
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
                    'status' => 'confirmed',
                    'provider_reference' => $result->providerReference ?? $payment->provider_reference,
                    'webhook_payload' => $webhookPayload,
                    'confirmed_at' => $payment->confirmed_at ?? now(),
                    'paid_at' => $payment->paid_at ?? now(),
                ]);

                // Legacy-data safety net: payments created before P4.2 carry the
                // enrollment in enrollment_pending_payload. No code writes this
                // payload any more — delete this branch in the cleanup deploy
                // once no non-final payment holds one.
                if ($payment->enrollment_pending_payload) {
                    $this->finalizeDeferredEnrollment($payment->fresh());
                }

                // P4.2: money→access happens HERE — domains activate
                // enrollments / grant access by listening, inside this
                // transaction (a failed listener rolls back with the payment).
                //
                // §41: the confirmation notices are listeners on this same
                // event now (`SendPaymentConfirmationNotices`), deferred to
                // after commit, rather than four hand-calls on the next line.
                // That is why an admin recording cash now reaches the family:
                // `RecordManualPaymentAction` fires this event too.
                event(new PaymentConfirmed($payment->fresh()));

                app(RecordInvoiceReceiptAction::class)->fromConfirmedPayment($payment->fresh());
                $this->recordPaymentCompletedFunnel($payment->fresh());
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
     * Website conversion: payment_completed only after webhook/provider confirmation.
     */
    private function recordPaymentCompletedFunnel(Payment $payment): void
    {
        $courseIds = [];
        if ($payment->course_id) {
            $courseIds[] = (int) $payment->course_id;
        }

        $payment->loadMissing(['items.enrollment']);
        foreach ($payment->items as $item) {
            if ($item->course_id) {
                $courseIds[] = (int) $item->course_id;
            } elseif ($item->enrollment?->course_id) {
                $courseIds[] = (int) $item->enrollment->course_id;
            }
        }

        $action = app(RecordFunnelEventAction::class);
        foreach (array_values(array_unique($courseIds)) as $courseId) {
            $action->execute($courseId, 'payment_completed', 'webhook', [
                'payment_id' => $payment->id,
            ]);
        }
    }

    /**
     * Resolve EnrollmentService lazily (avoids circular DI) and create the deferred enrollment.
     */
    private function finalizeDeferredEnrollment(Payment $payment): void
    {
        try {
            app(\App\Domains\Admissions\Services\Enrollment\EnrollmentService::class)
                ->createEnrollmentForConfirmedPayment($payment);
        } catch (\Throwable $e) {
            Log::error('PaymentService: deferred enrollment creation failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
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
            'status' => $payment->status,
            'confirmed' => $payment->isConfirmed(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'merchant_reference' => $payment->local_id ?? $payment->merchant_reference,
        ];
    }
}
