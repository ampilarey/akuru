<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;

interface PaymentProviderInterface
{
    public function initiate(Payment $payment, array $context = []): PaymentInitiationResult;

    public function verifyCallback(Request $request): PaymentVerificationResult;

    public function queryStatus(string $merchantReference): ?PaymentVerificationResult;
}
