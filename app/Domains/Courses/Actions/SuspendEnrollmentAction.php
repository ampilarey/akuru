<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Offerings\Actions\ReserveOfferingSeatAction;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §23's sixth enrollment status, which the database could not hold and
 * nothing could set.
 *
 * §23's seat rule is written around it — "Cancelled/**suspended** enrollments
 * should not count as active seats" — so the status was referenced by a rule
 * and existed nowhere else in the system.
 *
 * ## Why suspend rather than cancel
 *
 * `CancelEnrollmentAction` already exists and is what a *withdrawal* is: the
 * student is off the course. Suspension is the reversible one — unpaid fees, a
 * disciplinary hold, a place kept while something is sorted out — and the
 * difference that matters is the seat. A cancelled enrolment frees its seat for
 * somebody else; §23 says a suspended one does too, which is the point of
 * suspending rather than leaving the student active.
 *
 * Access falls away on its own. Every reader — `AuthorizeLessonAccessAction`,
 * `AuthorizeAssessmentAccessAction`, `ListStudentDashboardAction`,
 * `SyncEnrollmentProgressAction` — asks for `['active', 'approved',
 * 'completed']` by name rather than excluding a deny-list, so a status they do
 * not name is denied by construction. That is worth stating because it is the
 * reason this slice adds no guard to any of them: an allow-list is safe to
 * extend the vocabulary around, and a deny-list would not have been.
 *
 * Nothing of the student's is touched. Progress, attempts, attendance and
 * certificates stay exactly where they are (§29), which is what makes
 * reinstating meaningful rather than a fresh start.
 */
class SuspendEnrollmentAction
{
    /**
     * Statuses a suspension can be applied to.
     *
     * A cancelled or rejected enrolment is already off the course, and
     * suspending it would imply a seat it does not hold.
     *
     * @var list<string>
     */
    public const SUSPENDABLE = ['pending', 'approved', 'active', 'completed'];

    public function execute(CourseEnrollment $enrollment, ?string $reason = null): CourseEnrollment
    {
        if (! in_array((string) $enrollment->status, self::SUSPENDABLE, true)) {
            throw ValidationException::withMessages([
                'status' => 'Only a live enrolment can be suspended; this one is '.$enrollment->status.'.',
            ]);
        }

        $enrollment->status = 'suspended';
        $enrollment->save();

        return $enrollment->refresh();
    }

    /**
     * Put a suspended enrolment back.
     *
     * It returns to `active` rather than to whatever it was before, because the
     * previous status is not recorded anywhere — and inventing a column to
     * remember it would be a bigger change than this rule needs. An enrolment
     * that was `pending` before suspension and comes back `active` is a
     * decision an admin can see and undo; a guess stored in a new column is not.
     */
    public function reinstate(CourseEnrollment $enrollment): CourseEnrollment
    {
        if ((string) $enrollment->status !== 'suspended') {
            throw ValidationException::withMessages([
                'status' => 'Only a suspended enrolment can be reinstated.',
            ]);
        }

        // The seat was released while suspended, so it has to be available
        // again — reinstating into a full offering would put the offering over
        // its own limit, which is exactly what §23's rule exists to prevent.
        if ($enrollment->course_offering_id) {
            app(ReserveOfferingSeatAction::class)
                ->execute((int) $enrollment->course_offering_id);
        }

        $enrollment->status = 'active';
        $enrollment->save();

        return $enrollment->refresh();
    }
}
