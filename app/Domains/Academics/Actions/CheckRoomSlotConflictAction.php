<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\Period;
use App\Domains\Academics\Models\RoomBooking;
use App\Domains\Academics\Models\Timetable;
use App\Domains\Academics\Services\RoomBookingClashChecker;
use Carbon\Carbon;

class CheckRoomSlotConflictAction
{
    public function __construct(private RoomBookingClashChecker $checker) {}

    /**
     * Room + time conflicts against bookings and timetable slots.
     *
     * @return list<array{type: string, id: int}>
     */
    public function execute(int $yearId, int $roomId, string $date, ?string $start, ?string $end): array
    {
        if ($start === null || $end === null) {
            return [];
        }

        $day = strtolower(Carbon::parse($date)->englishDayOfWeek);

        return $this->checker->checkBooking(
            [
                'room_id' => $roomId,
                'academic_year_id' => $yearId,
                'date' => $date,
                'day_of_week' => $day,
                'start_time' => $start,
                'end_time' => $end,
            ],
            $this->bookings($yearId, $roomId, $date),
            $this->timetableEntries($yearId, $roomId, $day),
            $this->periodTimes(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bookings(int $yearId, int $roomId, string $date): array
    {
        return RoomBooking::query()
            ->where('academic_year_id', $yearId)
            ->where('room_id', $roomId)
            ->whereDate('date', $date)
            ->get()
            ->map(fn (RoomBooking $row) => [
                'id' => $row->id,
                'room_id' => $row->room_id,
                'academic_year_id' => $row->academic_year_id,
                'date' => $row->date?->toDateString(),
                'day_of_week' => strtolower(Carbon::parse($row->date)->englishDayOfWeek),
                'period_id' => $row->period_id,
                'start_time' => $row->start_time?->format('H:i:s'),
                'end_time' => $row->end_time?->format('H:i:s'),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timetableEntries(int $yearId, int $roomId, string $day): array
    {
        return Timetable::query()
            ->where('academic_year_id', $yearId)
            ->where('room_id', $roomId)
            ->where('day_of_week', $day)
            ->where('is_active', true)
            ->get()
            ->map(fn (Timetable $row) => [
                'id' => $row->id,
                'room_id' => $row->room_id,
                'academic_year_id' => $row->academic_year_id,
                'day_of_week' => $row->day_of_week,
                'period_id' => $row->period_id,
                'start_time' => $row->start_time?->format('H:i:s'),
                'end_time' => $row->end_time?->format('H:i:s'),
                'valid_from' => $row->valid_from?->toDateString(),
                'valid_until' => $row->valid_until?->toDateString(),
                'is_active' => $row->is_active,
            ])
            ->all();
    }

    /**
     * @return array<int, array{start: string, end: string}>
     */
    private function periodTimes(): array
    {
        $times = [];
        foreach (Period::query()->get(['id', 'start_time', 'end_time']) as $period) {
            $times[(int) $period->id] = [
                'start' => $period->start_time?->format('H:i:s') ?? '00:00:00',
                'end' => $period->end_time?->format('H:i:s') ?? '00:00:00',
            ];
        }

        return $times;
    }
}
