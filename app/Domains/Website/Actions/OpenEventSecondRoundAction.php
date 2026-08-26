<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Models\Event;
use App\Domains\Website\Models\EventRegistration;
use Illuminate\Support\Facades\DB;

class OpenEventSecondRoundAction
{
    /**
     * @return array{event: Event, promoted: int}
     */
    public function execute(int $eventId): array
    {
        return DB::transaction(function () use ($eventId): array {
            $event = Event::query()->lockForUpdate()->findOrFail($eventId);

            if ($event->second_round_opens_at === null) {
                $event->second_round_opens_at = now();
                $event->save();
            }

            $min = $event->min_attendees !== null ? (int) $event->min_attendees : null;
            $max = $event->max_attendees !== null ? (int) $event->max_attendees : null;

            $occupying = EventRegistration::query()
                ->where('event_id', $eventId)
                ->whereIn('status', RegisterForEventAction::OCCUPYING_STATUSES)
                ->lockForUpdate()
                ->count();

            $promoted = 0;
            if ($min !== null && $occupying < $min) {
                $waitlist = EventRegistration::query()
                    ->where('event_id', $eventId)
                    ->where('status', 'waitlisted')
                    ->orderBy('waitlist_position')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($waitlist as $registration) {
                    if ($occupying >= $min) {
                        break;
                    }
                    if ($max !== null && $occupying >= $max) {
                        break;
                    }

                    $status = $event->requires_parent_confirmation && $registration->student_id
                        ? 'pending_parent'
                        : 'confirmed';

                    $registration->update([
                        'status' => $status,
                        'waitlist_position' => null,
                        'confirmed_at' => $status === 'confirmed' ? now() : null,
                    ]);
                    $occupying++;
                    $promoted++;
                }
            }

            $event->updateAttendeeCount();

            return [
                'event' => $event->fresh(),
                'promoted' => $promoted,
            ];
        });
    }
}
