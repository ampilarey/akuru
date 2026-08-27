<?php

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
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
        if (! $ref) {
            return redirect()->route('public.courses.index')->with('error', 'Invalid payment reference.');
        }

        $payment = \App\Domains\Finance\Models\Payment::where('merchant_reference', $ref)->first();
        if (! $payment) {
            return redirect()->route('public.courses.index')->with('error', 'Payment not found.');
        }

        $result = $this->paymentService->initiatePayment($payment, [
            'return_url' => route('payments.bml.return').'?ref='.$ref,
        ]);

        if ($result->success && $result->redirectUrl) {
            session(['pending_payment_ref' => $ref]);

            return redirect()->away($result->redirectUrl);
        }

        return redirect()->route('courses.register.complete')
            ->with('error', $result->error ?? 'Payment initiation failed.');
    }

    /** Redirect return handler. BML appends ?transactionId=...&state=...&signature=...; display-only — finalization goes through PaymentService (P4.2: one confirmation path). */
    public function returnByPayment(Request $request, Payment $payment): View
    {
        $query = $request->query->all();
        $payment->update(['redirect_return_payload' => $query]);

        $bmlTransactionId = $request->query('transactionId') ?? $request->query('transaction_id');
        if ($bmlTransactionId && ! $payment->bml_transaction_id) {
            $payment->update(['bml_transaction_id' => $bmlTransactionId]);
        }

        if (in_array($payment->status, ['pending', 'initiated'], true)) {
            $payment = $this->paymentService->finalizeByReference($payment->merchant_reference) ?? $payment;
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

    public function callback(Request $request)
    {
        $this->paymentService->handleCallback($request);

        return response('OK', 200);
    }
}
