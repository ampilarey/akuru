<?php

namespace App\Domains\Admissions\DTOs;

/**
 * Everything the two free-enrollment notices need, as plain values.
 *
 * The paid half of this went through the same change one slice earlier
 * (`Finance\DTOs\PaymentNoticeData`), and for the same reason: SPEC §41 wants
 * Notifications to listen and send, and it could not, because both Mailables
 * took Eloquent models — a `CourseEnrollment` here, plus an Identity `User` for
 * the admin one. A Notifications listener holding either would be the exact
 * rule 3 violation §41 exists to prevent. Rule 3 permits a domain's DTOs across
 * the boundary, and this is one.
 *
 * It also takes two database queries out of a queued Blade template:
 * `admin-free-enrollment` was calling `$user->contacts()->where(...)` while
 * rendering, which is the same thing the paid admin mail was doing.
 */
final class FreeEnrollmentNoticeData
{
    public function __construct(
        public readonly int $enrollmentId,
        public readonly string $courseTitle,
        public readonly string $studentName,
        public readonly string $enrolledByName,
        public readonly ?string $enrolledAtLabel,
        public readonly string $payerName,
        public readonly ?string $payerEmail,
        public readonly ?string $payerMobile,
    ) {}

    /**
     * Mobile before email, which is the order the admin notice has always
     * shown and the order that actually reaches somebody here.
     */
    public function bestContact(): string
    {
        return $this->payerMobile ?? $this->payerEmail ?? '—';
    }
}
