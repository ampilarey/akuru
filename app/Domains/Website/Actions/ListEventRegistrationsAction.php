<?php

namespace App\Domains\Website\Actions;

use App\Domains\Website\Models\Event;
use App\Domains\Website\Models\EventRegistration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListEventRegistrationsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $eventId): Collection
    {
        Event::query()->findOrFail($eventId);

        $rows = EventRegistration::query()
            ->where('event_id', $eventId)
            ->orderByRaw("FIELD(status, 'pending_parent', 'pending', 'confirmed', 'waitlisted', 'attended', 'no_show', 'cancelled')")
            ->orderBy('waitlist_position')
            ->orderBy('id')
            ->get();

        $studentIds = $rows->pluck('student_id')->filter()->unique()->all();
        $students = $studentIds === []
            ? collect()
            : DB::table('students')->whereIn('id', $studentIds)->get()->keyBy('id');

        return $rows->map(function (EventRegistration $row) use ($students) {
            $student = $row->student_id ? $students->get($row->student_id) : null;

            return [
                'id' => $row->id,
                'event_id' => $row->event_id,
                'student_id' => $row->student_id,
                'student_name' => $student
                    ? trim(($student->first_name ?? '').' '.($student->last_name ?? ''))
                    : $row->name,
                'parent_user_id' => $row->parent_user_id,
                'name' => $row->name,
                'email' => $row->email,
                'phone' => $row->phone,
                'status' => $row->status,
                'waitlist_position' => $row->waitlist_position,
                'registration_source' => $row->registration_source,
                'academic_year_id' => $row->academic_year_id,
                'term_id' => $row->term_id,
                'created_at' => optional($row->created_at)->toDateTimeString(),
                'confirmed_at' => optional($row->confirmed_at)->toDateTimeString(),
            ];
        })->values();
    }
}
