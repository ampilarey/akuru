<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Offerings\Actions\ReserveOfferingSeatAction;
use Illuminate\Support\Facades\DB;

/**
 * Activating an enrolment from the admin screen has to pass the seat rule,
 * because activation is the moment it starts occupying one.
 *
 * `AdminEnrollmentController::activate()` was three lines:
 *
 *     $enrollment->update(['status' => 'active', 'enrolled_at' => ...]);
 *
 * No Action, no seat check, no look at where the row was coming from. The
 * statuses that occupy a seat are `active`, `approved`, `pending` and
 * `completed` (`ReserveOfferingSeatAction`) — so `rejected`, `cancelled` and
 * `suspended` do not. The Blade screen shows "Activate enrolment" on anything
 * that is not already active, **including a rejected one**, and one click moved
 * it into an occupying status without ever consulting the limit.
 *
 * SPEC §23's seat rule was enforced where a family enrols and skipped where an
 * administrator changes their mind. A full class could be oversold from the
 * admin screen, quietly, one reinstatement at a time.
 *
 * **Only the crossing is charged.** Activating an enrolment that is already
 * `pending` moves it between two occupying statuses: it is holding its seat
 * already, and asking for another would refuse a legitimate activation on a
 * class that is exactly full. That distinction is the whole of the rule here.
 */
class ActivateEnrollmentAction
{
    /** Statuses that already hold a seat — `ReserveOfferingSeatAction`'s list. */
    private const OCCUPYING = ['active', 'approved', 'pending', 'completed'];

    public function execute(CourseEnrollment $enrollment): CourseEnrollment
    {
        if ($enrollment->status === 'active') {
            return $enrollment;
        }

        $wasOccupying = in_array((string) $enrollment->status, self::OCCUPYING, true);

        return DB::transaction(function () use ($enrollment, $wasOccupying): CourseEnrollment {
            // The reservation locks the offering row and counts the occupying
            // enrolments; the status change has to happen inside the same
            // transaction so that lock still holds when this row joins them.
            if (! $wasOccupying && $enrollment->course_offering_id !== null) {
                app(ReserveOfferingSeatAction::class)->execute((int) $enrollment->course_offering_id);
            }

            $enrollment->update([
                'status' => 'active',
                'enrolled_at' => $enrollment->enrolled_at ?? now(),
            ]);

            return $enrollment->refresh();
        });
    }
}
