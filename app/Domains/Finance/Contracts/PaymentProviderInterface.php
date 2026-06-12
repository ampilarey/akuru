<?php

namespace App\Domains\Finance\Contracts;

use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Services\Payment\PaymentInitiationResult;
use App\Domains\Finance\Services\Payment\PaymentVerificationResult;
use Illuminate\Http\Request;

interface PaymentProviderInterface
{
    public function initiate(Payment $payment, array $context = []): PaymentInitiationResult;

    public function verifyCallback(Request $request): PaymentVerificationResult;

    public function queryStatus(string $merchantReference): ?PaymentVerificationResult;
}
