<?php

namespace App\Services\Enrollment;

use App\Models\CourseEnrollment;
use App\Models\Payment;

class EnrollmentResult
{
    /** @var CourseEnrollment[] */
    public array $createdEnrollments = [];

    /** @var CourseEnrollment[] */
    public array $existingEnrollments = [];

    /** @var Payment[] */
    public array $paymentsInitiated = [];

    public function hasPaymentsPending(): bool
    {
        return count($this->paymentsInitiated) > 0;
    }

    public function getConsolidatedPayment(): ?Payment
    {
        return $this->paymentsInitiated[0] ?? null;
    }

    public function allEnrollments(): array
    {
        return array_merge($this->createdEnrollments, $this->existingEnrollments);
    }
}
