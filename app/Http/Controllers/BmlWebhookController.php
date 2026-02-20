<?php

namespace App\Http\Controllers;

use App\Services\BmlConnectService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class BmlWebhookController extends Controller
{
    public function __construct(
        protected BmlConnectService $bml,
        protected PaymentService    $paymentService,
    ) {}

    /**
     * BML webhook endpoint — primary method for payment confirmation.
     * Delegates to PaymentService::handleCallback() for idempotent processing,
     * enrollment activation, and confirmation email dispatch.
     */
    public function __invoke(Request $request): Response
    {
        $log = fn ($msg, $ctx = []) => Log::channel('payments')->info($msg, $ctx);

        // Optional IP allowlist check
        if (! $this->bml->isWebhookIpAllowed($request)) {
            $log('BML webhook: IP not allowed', ['ip' => $request->ip()]);
            return response('Forbidden', 403);
        }

        $log('BML webhook received', [
            'ip'      => $request->ip(),
            'payload' => $request->getContent(),
        ]);

        try {
            // PaymentService::handleCallback() verifies signature, finds payment,
            // updates status, activates enrollments, and sends confirmation email.
            $this->paymentService->handleCallback($request);
        } catch (\Throwable $e) {
            $log('BML webhook: handleCallback threw', ['error' => $e->getMessage()]);
            // Return 200 anyway so BML does not keep retrying on application errors
        }

        return response('OK', 200);
    }
}
