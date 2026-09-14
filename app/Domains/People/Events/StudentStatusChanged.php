<?php

namespace App\Domains\People\Events;

use App\Domains\People\Enums\StudentStatus;

/**
 * A student's standing with the Institute changed.
 *
 * Raised by `ChangeStudentStatusAction`, which is the only writer of
 * `students.status` — the column is deliberately not fillable so that every
 * change leaves a history row, and now so that every change is announced.
 *
 * People does not know what anybody else keeps about a student, and must not:
 * the class roster belongs to Academics, and a cascade written here would be
 * People writing another domain's table (rule 3). Whoever holds a record that
 * should end when a pupil leaves listens for this in their own provider, which
 * is the shape `StudentMarkedAbsent` → `SendAbsenceSms` already uses.
 */
class StudentStatusChanged
{
    public function __construct(
        public int $studentId,
        public ?StudentStatus $from,
        public StudentStatus $to,
        public string $effectiveDate,
        public int $changedBy,
    ) {}

    /**
     * Whether this change means the pupil has left the Institute.
     *
     * Three of the six statuses are departures, and they are the three
     * `PromoteStudentsAction` already pairs with closing a class placement:
     * a promotion run that graduates a pupil closes their roster row in the
     * same transaction. This says the same thing about the mid-year path,
     * which had no such pairing.
     *
     * `inactive` is **not** a departure and is deliberately excluded: it
     * records a pupil who has stopped attending without leaving, and a school
     * chasing that absence needs them on the register to do it. `prospective`
     * is not one either — an applicant has not arrived yet.
     */
    public function isDeparture(): bool
    {
        return in_array($this->to, [
            StudentStatus::Graduated,
            StudentStatus::Transferred,
            StudentStatus::Withdrawn,
        ], true);
    }
}
