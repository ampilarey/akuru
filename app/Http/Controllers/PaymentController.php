<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\BmlConnectService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected BmlConnectService $bml
    ) {}

    /** Status poll for return page (JSON). */
    public function statusByPayment(Payment $payment): JsonResponse
    {
        return response()->json([
            'status' => $payment->status,
            'confirmed' => $payment->isConfirmed(),
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ]);
    }

    /** Legacy status by merchant_reference. */
    public function status(string $merchantReference): JsonResponse
    {
        $data = $this->paymentService->getPaymentStatus($merchantReference);
        if (! $data) {
            return response()->json(['error' => 'Payment not found'], 404);
        }
        return response()->json($data);
    }

    public function initiate(Request $request)
    {
        $ref = $request->input('merchant_reference') ?? session('pending_payment_ref');
        if (!$ref) {
            return redirect()->route('public.courses.index')->with('error', 'Invalid payment reference.');
        }

        $payment = \App\Models\Payment::where('merchant_reference', $ref)->first();
        if (!$payment) {
            return redirect()->route('public.courses.index')->with('error', 'Payment not found.');
        }

        $result = $this->paymentService->initiatePayment($payment, [
            'return_url' => route('payments.bml.return') . '?ref=' . $ref,
        ]);

        if ($result->success && $result->redirectUrl) {
            session(['pending_payment_ref' => $ref]);
            return redirect()->away($result->redirectUrl);
        }

        return redirect()->route('courses.register.complete')
            ->with('error', $result->error ?? 'Payment initiation failed.');
    }

    /** Redirect return handler. BML appends ?transactionId=...&state=...&signature=...; we optionally fetch final state via Get Transaction. */
    public function returnByPayment(Request $request, Payment $payment): View
    {
        $query = $request->query->all();
        $payment->update(['redirect_return_payload' => $query]);

        $bmlTransactionId = $request->query('transactionId') ?? $request->query('transaction_id');
        if ($bmlTransactionId && $this->bml && in_array($payment->status, ['pending', 'initiated'], true)) {
            $this->applyBmlTransactionStatus($payment, $bmlTransactionId);
        }
        if (! $payment->bml_transaction_id && $bmlTransactionId) {
            $payment->update(['bml_transaction_id' => $bmlTransactionId]);
        }

        return view('payments.processing', ['payment' => $payment]);
    }

    /**
     * Sessionless return endpoint. Works without session by accepting ?ref= from URL.
     *
     * BML redirects here after payment. We finalizeByReference() server-side so the
     * result is authoritative regardless of whether the user's session is still alive.
     */
    public function return(Request $request)
    {
        // Accept ref from URL first (BML appends it), fall back to session for older flows
        $ref = $request->query('ref') ?? session('pending_payment_ref');

        if (! $ref) {
            return view('payments.return-missing');
        }

        $payment = Payment::where('merchant_reference', $ref)->orWhere('local_id', $ref)->first();

        if (! $payment) {
            return view('payments.return-missing', ['ref' => $ref]);
        }

        // Store BML-appended query params for debugging
        $payment->update(['redirect_return_payload' => $request->query->all()]);

        // Pre-set BML transaction id if present
        $bmlTransactionId = $request->query('transactionId') ?? $request->query('transaction_id');
        if ($bmlTransactionId && ! $payment->bml_transaction_id) {
            $payment->update(['bml_transaction_id' => $bmlTransactionId]);
        }

        // Finalize server-side (idempotent); ignores return URL state entirely
        $payment = $this->paymentService->finalizeByReference($ref) ?? $payment;

        return view('payments.processing', ['payment' => $payment]);
    }

    /** Fetch BML transaction state and update payment (same logic as reconciliation). */
    protected function applyBmlTransactionStatus(Payment $payment, string $bmlTransactionId): void
    {
        $data = $this->bml->getTransactionStatus($bmlTransactionId);
        if (! $data) {
            return;
        }
        $state = $data['state'] ?? $data['status'] ?? $data['transactionStatus'] ?? null;
        if ($state === null) {
            return;
        }
        $newStatus = $this->bml->mapWebhookStatusToPaymentStatus((string) $state);
        if ($newStatus === 'pending') {
            return;
        }
        DB::transaction(function () use ($payment, $newStatus, $data) {
            $payment->lockForUpdate();
            $payment->bml_status_raw = array_merge($payment->bml_status_raw ?? [], $data);
            $payment->status = $newStatus;
            if ($newStatus === 'confirmed') {
                $payment->paid_at = $payment->paid_at ?? now();
                $payment->confirmed_at = $payment->confirmed_at ?? $payment->paid_at;
                $payment->save();

                // Deferred-enrollment flow
                if ($payment->enrollment_pending_payload) {
                    app(\App\Services\Enrollment\EnrollmentService::class)
                        ->createEnrollmentForConfirmedPayment($payment->fresh());
                } else {
                    foreach ($payment->items as $item) {
                        $enrollment = $item->enrollment;
                        if ($enrollment) {
                            $enrollment->update(['payment_status' => 'confirmed', 'payment_id' => $payment->id]);
                            $course = $enrollment->course;
                            if (! ($course->requires_admin_approval ?? false)) {
                                $enrollment->update([
                                    'status'      => 'active',
                                    'enrolled_at' => $enrollment->enrolled_at ?? now(),
                                ]);
                            }
                        }
                    }
                }
            } elseif ($newStatus === 'failed') {
                $payment->failed_at = now();
                $payment->save();
            } else {
                $payment->save();
            }
        });
    }

    public function callback(Request $request)
    {
        $this->paymentService->handleCallback($request);
        return response('OK', 200);
    }
}
