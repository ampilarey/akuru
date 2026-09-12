<?php

namespace App\Domains\Offerings\Actions;

use App\Domains\Offerings\Enums\OfferingStatus;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Validation\ValidationException;

class TransitionOfferingStatusAction
{
    /**
     * SPEC §11.4: "Invalid transitions must be rejected."
     *
     * Nothing rejected anything. `SaveCourseOfferingAction` took whatever
     * `status` arrived, ran it through `tryFrom`, and stored it — so an
     * offering could go from Archived back to Draft, or from Completed to
     * Open, in one request. A finished cohort could be reopened for enrolment
     * by a mistyped form field, and nothing would say so.
     *
     * Moving to the state an offering is already in is allowed and does
     * nothing. A no-op is not an invalid transition, and treating it as one
     * makes every idempotent caller — a retry, a double-submitted form —
     * into an error the operator has to interpret.
     */
    public function execute(CourseOffering $offering, OfferingStatus $to, ?int $actorId = null): CourseOffering
    {
        $from = $offering->status instanceof OfferingStatus
            ? $offering->status
            : (OfferingStatus::tryFrom((string) $offering->status) ?? OfferingStatus::Draft);

        if ($from === $to) {
            return $offering;
        }

        if (! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'An offering cannot go from %s to %s.',
                    $from->label(),
                    $to->label(),
                ),
            ]);
        }

        $offering->status = $to;
        $offering->save();

        return $offering->refresh();
    }
}
