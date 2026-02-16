<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\BmlConnectService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BmlWebhookController extends Controller
{
    public function __construct(
        protected BmlConnectService $bml
    ) {}

    /**
     * Webhook endpoint (PRIMARY method for payment confirmation). Redirect is not authoritative.
     */
    public function __invoke(Request $request): Response
    {
        $log = fn ($msg, $ctx = []) => Log::channel('payments')->info($msg, $ctx);

        if (! $this->bml->isWebhookIpAllowed($request)) {
            $log('BML webhook: IP not allowed', ['ip' => $request->ip()]);
            return response('', 403);
        }

        $secret = config('bml.webhook_secret');
        if ($secret && ! $this->bml->verifyWebhookSignature($request)) {
            $log('BML webhook: Invalid signature');
            return response('', 400);
        }

        $payload = $request->all();
        if (empty($payload)) {
            $payload = json_decode($request->getContent(), true) ?? [];
        }
        $parsed = $this->bml->parseWebhookPayload($payload);
        $transactionId = $parsed['transaction_id'];
        $localId = $parsed['local_id'];

        if (! $transactionId && ! $localId) {
            $log('BML webhook: No transaction_id or local_id', ['payload' => $payload]);
            return response('', 400);
        }

        $payment = $transactionId
            ? Payment::where('bml_transaction_id', $transactionId)->first()
            : null;
        if (! $payment && $localId) {
            $payment = Payment::where('local_id', $localId)->first();
        }
        if (! $payment) {
            $log('BML webhook: Payment not found', ['transaction_id' => $transactionId, 'local_id' => $localId]);
            return response('', 200);
        }

        $paymentId = $payment->id;

        DB::transaction(function () use ($paymentId, $parsed, $log) {
            $payment = Payment::where('id', $paymentId)->lockForUpdate()->first();
            if (! $payment) {
                return;
            }
            $payment->webhook_payload = $parsed['raw'];
            $payment->bml_status_raw = array_merge($payment->bml_status_raw ?? [], $parsed['raw']);

            $newStatus = $this->bml->mapWebhookStatusToPaymentStatus((string) ($parsed['status'] ?? ''));
            $alreadyPaid = $payment->isPaid();

            if ($alreadyPaid && $newStatus === 'confirmed') {
                $log('BML webhook: Idempotent - already paid', ['local_id' => $payment->local_id]);
                $payment->save();
                return;
            }

            $payment->status = $newStatus;
            if ($newStatus === 'confirmed') {
                $payment->paid_at = $parsed['paid_at'] ?? now();
                $payment->confirmed_at = $payment->paid_at;
            } elseif ($newStatus === 'failed') {
                $payment->failed_at = now();
            }
            $payment->save();

            if ($newStatus === 'confirmed' && ! $alreadyPaid) {
                $this->finalizeEnrollment($payment);
                $log('BML webhook: Payment finalized', ['local_id' => $payment->local_id]);
            }
        });

        return response('', 200);
    }

    private function finalizeEnrollment(Payment $payment): void
    {
        foreach ($payment->items as $item) {
            $enrollment = $item->enrollment;
            $enrollment->update(['payment_status' => 'confirmed', 'payment_id' => $payment->id]);
            $course = $enrollment->course;
            if (! ($course->requires_admin_approval ?? false)) {
                $enrollment->update([
                    'status' => 'active',
                    'enrolled_at' => $enrollment->enrolled_at ?? now(),
                ]);
            }
        }
    }
}
