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
            return redirect()->route('public.courses.index')->with('error', __('payments.error_reference'));
        }

        $payment = \App\Domains\Finance\Models\Payment::where('merchant_reference', $ref)->first();
        if (! $payment) {
            return redirect()->route('public.courses.index')->with('error', __('register.error_payment_missing'));
        }

        $result = $this->paymentService->initiatePayment($payment, [
            'return_url' => route('payments.bml.return').'?ref='.$ref,
        ]);

        if ($result->success && $result->redirectUrl) {
            session(['pending_payment_ref' => $ref]);

            return redirect()->away($result->redirectUrl);
        }

        return redirect()->route('courses.register.complete')
            ->with('error', $result->error ?? __('register.error_payment_start'));
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

        return $this->processing($payment);
    }

    /**
     * Sessionless return endpoint. Works without session by accepting ?ref= from URL.
     *
     * BML redirects here after payment. We finalizeByReference() server-side so the
     * result is authoritative regardless of whether the user's session is still alive.
     */
    public function return(Request $request)
    {
        $this->speakTheRememberedLanguage($request);

        // Accept ref from URL first (BML appends it), fall back to session for older flows
        $ref = $request->query('ref') ?? session('pending_payment_ref');

        if (! $ref) {
            return view('payments.return-missing');
        }

        $payment = Payment::where('merchant_reference', $ref)->orWhere('local_id', $ref)->first();

        if (! $payment) {
            return view('payments.return-missing', ['ref' => $ref]);
        }

        // Store BML-appended query params for debugging. Written, never read
        // for a decision — nothing outside this line and the model's casts
        // touches the column.
        $payment->update(['redirect_return_payload' => $request->query->all()]);

        // **A hint, and nothing more.** This route is `['web']` only: no auth,
        // no signature, no ownership check, every value from the query string.
        // BML knows transactions by their own id rather than our merchant
        // reference, so the id is genuinely needed to ask BML about this
        // payment — but it is an *untrusted* pointer to the question, never
        // the answer.
        //
        // What makes that safe is on the other side:
        // `PaymentService::finalizeByReference` compares the merchant
        // reference BML returns against this payment and refuses a mismatch.
        // Without that check, replaying any completed transaction id here
        // confirmed this payment — see the comment there.
        $bmlTransactionId = $request->query('transactionId') ?? $request->query('transaction_id');
        if ($bmlTransactionId && ! $payment->bml_transaction_id) {
            $payment->update(['bml_transaction_id' => $bmlTransactionId]);
        }

        // Finalize server-side (idempotent); ignores return URL state entirely
        $payment = $this->paymentService->finalizeByReference($ref) ?? $payment;

        return $this->processing($payment);
    }

    public function callback(Request $request)
    {
        $this->paymentService->handleCallback($request);

        return response('OK', 200);
    }

    /**
     * The page back from the bank (slice PY1). A payment the bank turned down
     * is said at once, where the page used to wait a minute and then say
     * "If your payment was completed…".
     */
    private function processing(Payment $payment): View
    {
        return view('payments.processing', [
            'payment' => $payment,
            'notPaid' => $payment->didNotGoThrough(),
            'nextHref' => $this->nextHref($payment),
        ]);
    }

    /**
     * Back to what the payment was for. Every payment was sent on to the
     * registration's last page, which only a registration's own payment has
     * the session for: a family paying a fee, or a learner who bought a
     * course from the learner catalogue, was sent from there to the course
     * list with an error (slice PY1).
     */
    private function nextHref(Payment $payment): string
    {
        return match (true) {
            $payment->getRawOriginal('payable_type') === 'invoice' => route('portal.invoices'),
            session()->has('pending_payment_ref') || session()->has('enrollments') => route('courses.register.complete'),
            default => route('my.enrollments'),
        };
    }

    /**
     * The bank's return lands outside the localised routes, so that no locale
     * redirect drops its query string; it ran in the default language, and a
     * family who paid from a Dhivehi page came back to English. It speaks
     * the language the session remembers from the site's own pages, or the
     * browser's, as the certificate check does (slice PY1).
     */
    private function speakTheRememberedLanguage(Request $request): void
    {
        $supported = array_keys((array) config('laravellocalization.supportedLocales', []));
        $remembered = $request->hasSession() ? $request->session()->get('locale') : null;
        $locale = in_array($remembered, $supported, true) ? $remembered : $request->getPreferredLanguage($supported);

        app()->setLocale($locale ?? config('app.locale'));
    }
}
