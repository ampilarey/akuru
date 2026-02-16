<?php

namespace App\Services\Payment;

class PaymentInitiationResult
{
    public function __construct(
        public bool $success,
        public ?string $redirectUrl = null,
        public ?string $formHtml = null,
        public ?string $error = null
    ) {}
}
