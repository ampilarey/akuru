<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListSchoolEventsAction
{
    /**
     * @param  array{academic_year_id?: int|null, status?: string|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $query = Event::query()->orderBy('start_date');

        if (! empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', (int) $filters['academic_year_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $events = $query->get();
        $ids = $events->pluck('id');

        $counts = DB::table('event_registrations')
            ->selectRaw('event_id, status, COUNT(*) as total')
            ->whereIn('event_id', $ids->all() ?: [0])
            ->groupBy('event_id', 'status')
            ->get()
            ->groupBy('event_id');

        return $events->map(function (Event $event) use ($counts) {
            $byStatus = collect($counts->get($event->id, collect()))
                ->mapWithKeys(fn ($row) => [(string) $row->status => (int) $row->total]);

            $occupying = (int) $byStatus->only(RegisterForEventAction::OCCUPYING_STATUSES)->sum();

            return [
                'id' => $event->id,
                'title' => $event->title,
                'title_dv' => $event->title_dv,
                'title_ar' => $event->title_ar,
                'slug' => $event->slug,
                'description' => $event->description,
                'short_description' => $event->short_description,
                'location' => $event->location,
                'start_date' => optional($event->start_date)->toDateTimeString(),
                'end_date' => optional($event->end_date)->toDateTimeString(),
                'type' => $event->type,
                'status' => $event->status,
                'registration_type' => $event->registration_type,
                'max_attendees' => $event->max_attendees,
                'min_attendees' => $event->min_attendees,
                'waitlist_enabled' => (bool) $event->waitlist_enabled,
                'requires_parent_confirmation' => (bool) $event->requires_parent_confirmation,
                'second_round_opens_at' => optional($event->second_round_opens_at)->toDateTimeString(),
                'is_elective' => (bool) $event->is_elective,
                'is_public' => (bool) $event->is_public,
                'academic_year_id' => $event->academic_year_id,
                'occupying' => $occupying,
                'waitlisted' => (int) ($byStatus['waitlisted'] ?? 0),
                'confirmed' => (int) ($byStatus['confirmed'] ?? 0),
                'pending_parent' => (int) ($byStatus['pending_parent'] ?? 0),
                'spots_remaining' => $event->max_attendees === null
                    ? null
                    : max(0, (int) $event->max_attendees - $occupying),
            ];
        })->values();
    }
}
