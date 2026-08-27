<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\MeetingBookingStatus;
use App\Domains\Academics\Models\MeetingSlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListMeetingSlotsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $academicYearId = null): Collection
    {
        $slots = MeetingSlot::query()
            ->with(['bookings'])
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return $this->serialize($slots);
    }

    /**
     * @param  Collection<int, MeetingSlot>  $slots
     * @return Collection<int, array<string, mixed>>
     */
    public function serialize(Collection $slots): Collection
    {
        $teachers = DB::table('teachers')
            ->whereIn('id', $slots->pluck('teacher_id')->unique()->filter())
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');
        $classes = DB::table('classes')
            ->whereIn('id', $slots->pluck('class_id')->unique()->filter())
            ->get(['id', 'name', 'section'])
            ->keyBy('id');
        $rooms = DB::table('rooms')
            ->whereIn('id', $slots->pluck('room_id')->unique()->filter())
            ->get(['id', 'name'])
            ->keyBy('id');
        $students = DB::table('students')
            ->whereIn('id', $slots->flatMap->bookings->pluck('student_id')->unique()->filter())
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        return $slots->map(function (MeetingSlot $slot) use ($teachers, $classes, $rooms, $students): array {
            $teacher = $teachers[$slot->teacher_id] ?? null;
            $class = $slot->class_id ? ($classes[$slot->class_id] ?? null) : null;
            $room = $slot->room_id ? ($rooms[$slot->room_id] ?? null) : null;
            $booked = $slot->bookings->filter(
                fn ($booking) => ($booking->status?->value ?? (string) $booking->status) === MeetingBookingStatus::Booked->value,
            );

            return [
                'id' => $slot->id,
                'academic_year_id' => $slot->academic_year_id,
                'term_id' => $slot->term_id,
                'teacher_id' => $slot->teacher_id,
                'teacher_name' => trim(($teacher->first_name ?? '').' '.($teacher->last_name ?? '')),
                'class_id' => $slot->class_id,
                'class_name' => $class ? trim(($class->name ?? '').' '.($class->section ?? '')) : null,
                'room_id' => $slot->room_id,
                'room_name' => $room->name ?? null,
                'title' => $slot->title,
                'title_arabic' => $slot->title_arabic,
                'title_dhivehi' => $slot->title_dhivehi,
                'date' => $slot->date?->toDateString(),
                'start_time' => $slot->start_time?->format('H:i'),
                'end_time' => $slot->end_time?->format('H:i'),
                'capacity' => (int) $slot->capacity,
                'booked' => $booked->count(),
                'remaining' => max(0, (int) $slot->capacity - $booked->count()),
                'status' => $slot->status?->value ?? (string) $slot->status,
                'notes' => $slot->notes,
                'bookings' => $booked->map(function ($booking) use ($students): array {
                    $student = $students[$booking->student_id] ?? null;

                    return [
                        'id' => $booking->id,
                        'student_id' => $booking->student_id,
                        'student_name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                        'status' => $booking->status?->value ?? (string) $booking->status,
                    ];
                })->values()->all(),
            ];
        })->values();
    }
}
