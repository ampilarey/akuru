<?php

namespace App\Domains\Finance\Services\Payment;

class PaymentVerificationResult
{
    public function __construct(
        public bool $verified,
        public ?string $merchantReference = null,
        public ?string $providerReference = null,
        public ?string $status = null,
        public ?array $rawPayload = null,
        public ?string $error = null,
        public bool $isConfirmed = false,
        // STATUS §5lw: the callback proved it came from BML but not what it
        // says (BML's signature does not cover the body), so the payment is
        // confirmed only by asking BML's API, never from this payload.
        public bool $confirmWithProvider = false,
    ) {}

    public function isPaymentSuccess(): bool
    {
        return $this->verified && $this->isConfirmed;
    }
}
