<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\Academics\Enums\MeetingBookingStatus;
use App\Domains\Academics\Enums\MeetingSlotStatus;
use App\Domains\Academics\Models\MeetingBooking;
use App\Domains\Academics\Models\MeetingSlot;
use Illuminate\Support\Facades\DB;

class ListPortalMeetingSlotsAction
{
    /**
     * @param  list<int>  $studentIds
     * @return array{slots: list<array<string, mixed>>, bookings: list<array<string, mixed>>}
     */
    public function execute(array $studentIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if ($ids === []) {
            return ['slots' => [], 'bookings' => []];
        }

        $today = now()->toDateString();
        $classIds = DB::table('class_student')
            ->whereIn('student_id', $ids)
            ->where('status', ClassStudentStatus::Active->value)
            ->get(['student_id', 'class_id']);

        $slots = MeetingSlot::query()
            ->with(['bookings'])
            ->where('status', MeetingSlotStatus::Published)
            ->whereDate('date', '>=', $today)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(function (MeetingSlot $slot) use ($classIds): bool {
                if ($slot->class_id === null) {
                    return true;
                }

                return $classIds->contains(fn ($row) => (int) $row->class_id === (int) $slot->class_id);
            })
            ->values();

        $serializedSlots = app(ListMeetingSlotsAction::class)->serialize($slots)->map(function (array $row) use ($ids, $classIds): array {
            $eligible = $ids;
            if ($row['class_id'] !== null) {
                $eligible = $classIds
                    ->filter(fn ($item) => (int) $item->class_id === (int) $row['class_id'])
                    ->pluck('student_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }
            $bookedFor = collect($row['bookings'])
                ->pluck('student_id')
                ->map(fn ($id) => (int) $id)
                ->intersect($ids)
                ->values()
                ->all();

            $row['eligible_student_ids'] = array_values(array_unique($eligible));
            $row['booked_student_ids'] = $bookedFor;
            $row['can_book'] = $row['remaining'] > 0 && $eligible !== [];

            return $row;
        })->all();

        $bookings = MeetingBooking::query()
            ->with('slot')
            ->whereIn('student_id', $ids)
            ->where('status', MeetingBookingStatus::Booked)
            ->orderByDesc('id')
            ->get();

        $slotRows = app(ListMeetingSlotsAction::class)
            ->serialize($bookings->pluck('slot')->filter()->values())
            ->keyBy('id');

        $students = DB::table('students')
            ->whereIn('id', $ids)
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        $serializedBookings = $bookings->map(function (MeetingBooking $booking) use ($slotRows, $students): array {
            $slot = $slotRows[$booking->meeting_slot_id] ?? null;
            $student = $students[$booking->student_id] ?? null;

            return [
                'id' => $booking->id,
                'meeting_slot_id' => $booking->meeting_slot_id,
                'student_id' => $booking->student_id,
                'student_name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                'title' => $slot['title'] ?? '',
                'date' => $slot['date'] ?? null,
                'start_time' => $slot['start_time'] ?? null,
                'end_time' => $slot['end_time'] ?? null,
                'teacher_name' => $slot['teacher_name'] ?? '',
                'status' => $booking->status?->value ?? (string) $booking->status,
            ];
        })->values()->all();

        return [
            'slots' => $serializedSlots,
            'bookings' => $serializedBookings,
        ];
    }
}
