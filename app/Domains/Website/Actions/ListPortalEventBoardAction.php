<?php

namespace App\Domains\Website\Actions;

class ListPortalEventBoardAction
{
    /**
     * @param  list<int>  $childIds
     * @return array{events: list<array<string, mixed>>, registrations: list<array<string, mixed>>}
     */
    public function execute(array $childIds): array
    {
        $events = app(ListSchoolEventsAction::class)->execute(['status' => 'published'])
            ->filter(function (array $event): bool {
                if (! in_array($event['registration_type'], ['required', 'optional'], true)) {
                    return false;
                }

                return (bool) $event['is_elective'] || (bool) $event['is_public'];
            })
            ->values()
            ->all();

        $eventIds = array_column($events, 'id');
        $registrations = [];
        if ($childIds !== [] && $eventIds !== []) {
            foreach ($eventIds as $eventId) {
                foreach (app(ListEventRegistrationsAction::class)->execute((int) $eventId) as $row) {
                    if (in_array((int) $row['student_id'], $childIds, true)) {
                        $registrations[] = $row;
                    }
                }
            }
        }

        return [
            'events' => $events,
            'registrations' => $registrations,
        ];
    }
}
