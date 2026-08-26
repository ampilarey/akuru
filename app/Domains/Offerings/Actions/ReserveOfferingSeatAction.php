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
            occupyingStatuses: ['active', 'approved', 'pending', 'completed'],
            waitlistEnabledColumn: null,
            fullMessage: 'This offering has no remaining seats.',
        );

        return [
            'id' => (int) $seat['row']->id,
            'course_id' => (int) $seat['row']->course_id,
            'seat_limit' => $seat['row']->seat_limit !== null ? (int) $seat['row']->seat_limit : null,
        ];
    }
}
