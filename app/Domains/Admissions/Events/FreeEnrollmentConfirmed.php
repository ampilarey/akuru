<?php

namespace App\Domains\Admissions\Events;

use App\Domains\Admissions\DTOs\FreeEnrollmentNoticeData;

/**
 * A course enrollment that needs no payment has been created.
 *
 * This is the literal case SPEC §41 names — "Enrollment should dispatch an
 * enrollment-created event. Notifications should listen to that event." The
 * paid half goes through `Finance\Events\PaymentNoticeReady` instead, because
 * for a paid course the moment worth announcing is the money arriving, not the
 * row being written (rule 12: never announce before confirmation).
 *
 * It carries a DTO rather than the enrollment, so Notifications can listen
 * without importing another domain's model.
 */
class FreeEnrollmentConfirmed
{
    public function __construct(public FreeEnrollmentNoticeData $notice) {}
}
