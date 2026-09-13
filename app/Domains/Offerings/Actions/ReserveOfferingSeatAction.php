<?php

namespace App\Domains\Offerings\Actions;

class ReserveOfferingSeatAction
{
    /**
     * @return array{id: int, course_id: int, seat_limit: int|null}
     */
    public function execute(int $offeringId): array
    {
        $seat = app(EnforceSeatLimitAction::class)->execute(
            resourceTable: 'course_offerings',
            resourceId: $offeringId,
            limitColumn: 'seat_limit',
            occupancyTable: 'course_enrollments',
            foreignKey: 'course_offering_id',
            // SPEC §23: "Cancelled/suspended enrollments should not count as
            // active seats." Both are absent from this list — `cancelled`
            // always was, and `suspended` could not be until the status existed.
            occupyingStatuses: ['active', 'approved', 'pending', 'completed'],
            waitlistEnabledColumn: null,
            fullMessage: 'This offering has no remaining seats.',
            // `course_enrollments` soft-deletes (§29), and this counts through
            // the query builder for the row locks — which knows nothing about
            // the trait. A soft-deleted enrolment was holding its seat forever.
            respectSoftDeletes: true,
        );

        return [
            'id' => (int) $seat['row']->id,
            'course_id' => (int) $seat['row']->course_id,
            'seat_limit' => $seat['row']->seat_limit !== null ? (int) $seat['row']->seat_limit : null,
        ];
    }
}
