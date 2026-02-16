<?php

namespace App\Services\Payment;

class PaymentVerificationResult
{
    public function __construct(
        public bool $verified,
        public ?string $merchantReference = null,
        public ?string $providerReference = null,
        public ?string $status = null,
        public ?array $rawPayload = null,
        public ?string $error = null,
        public bool $isConfirmed = false
    ) {}

    public function isPaymentSuccess(): bool
    {
        return $this->verified && $this->isConfirmed;
    }
}
