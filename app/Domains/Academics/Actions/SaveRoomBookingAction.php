<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Exceptions\RoomBookingClashException;
use App\Domains\Academics\Models\Period;
use App\Domains\Academics\Models\Room;
use App\Domains\Academics\Models\RoomBooking;
use App\Domains\Academics\Models\Timetable;
use App\Domains\Academics\Services\RoomBookingClashChecker;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class SaveRoomBookingAction
{
    public function __construct(private RoomBookingClashChecker $checker) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?RoomBooking $booking = null, ?int $actorId = null): RoomBooking
    {
        $payload = $this->normalized($data, $actorId);

        $conflicts = $this->checker->checkBooking(
            $this->toCheckBooking($payload, $booking?->id),
            $this->existingBookings((int) $payload['academic_year_id'], (int) $payload['room_id'], (string) $payload['date']),
            $this->timetableEntries((int) $payload['academic_year_id'], (int) $payload['room_id'], (string) $payload['day_of_week']),
            $this->periodTimes(),
        );

        if ($conflicts !== []) {
            throw new RoomBookingClashException($conflicts);
        }

        unset($payload['day_of_week']);

        if ($booking === null) {
            return RoomBooking::query()->create($payload);
        }

        $booking->fill($payload);
        $booking->save();

        return $booking->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalized(array $data, ?int $actorId): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Title is required.']);
        }

        $roomId = (int) ($data['room_id'] ?? 0);
        $room = Room::query()->find($roomId);
        if ($room === null || ! $room->active || ! $room->bookable) {
            throw ValidationException::withMessages(['room_id' => 'Choose an active bookable room.']);
        }

        $periodId = $data['period_id'] ?? null;
        $periodId = $periodId === '' || $periodId === null ? null : (int) $periodId;
        $start = $this->nullableString($data['start_time'] ?? null);
        $end = $this->nullableString($data['end_time'] ?? null);

        $hasPeriod = $periodId !== null;
        $hasTimes = $start !== null && $end !== null;

        if ($hasPeriod === $hasTimes) {
            throw ValidationException::withMessages([
                'period_id' => 'Provide either a period or a start and end time, not both.',
            ]);
        }

        if ($hasPeriod) {
            $period = Period::query()->find($periodId);
            if ($period === null) {
                throw ValidationException::withMessages(['period_id' => 'Period not found.']);
            }
            $start = $period->start_time?->format('H:i:s');
            $end = $period->end_time?->format('H:i:s');
        }

        $date = Carbon::parse((string) $data['date'])->toDateString();

        return [
            'academic_year_id' => (int) $data['academic_year_id'],
            'term_id' => isset($data['term_id']) && $data['term_id'] !== '' && $data['term_id'] !== null
                ? (int) $data['term_id']
                : null,
            'room_id' => $roomId,
            'title' => $title,
            'title_arabic' => $this->nullableString($data['title_arabic'] ?? null),
            'title_dhivehi' => $this->nullableString($data['title_dhivehi'] ?? null),
            'date' => $date,
            'day_of_week' => strtolower(Carbon::parse($date)->englishDayOfWeek),
            'period_id' => $periodId,
            'start_time' => $start,
            'end_time' => $end,
            'notes' => $this->nullableString($data['notes'] ?? null),
            'booked_by' => $actorId,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function toCheckBooking(array $payload, ?int $id): array
    {
        return [
            'id' => $id,
            'room_id' => $payload['room_id'],
            'academic_year_id' => $payload['academic_year_id'],
            'date' => $payload['date'],
            'day_of_week' => $payload['day_of_week'],
            'period_id' => $payload['period_id'],
            'start_time' => $payload['start_time'],
            'end_time' => $payload['end_time'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function existingBookings(int $yearId, int $roomId, string $date): array
    {
        return RoomBooking::query()
            ->where('academic_year_id', $yearId)
            ->where('room_id', $roomId)
            ->whereDate('date', $date)
            ->get()
            ->map(fn (RoomBooking $row) => $this->serializeBooking($row))
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
     * @return array<string, mixed>
     */
    private function serializeBooking(RoomBooking $row): array
    {
        return [
            'id' => $row->id,
            'room_id' => $row->room_id,
            'academic_year_id' => $row->academic_year_id,
            'date' => $row->date?->toDateString(),
            'day_of_week' => strtolower(Carbon::parse($row->date)->englishDayOfWeek),
            'period_id' => $row->period_id,
            'start_time' => $row->start_time?->format('H:i:s'),
            'end_time' => $row->end_time?->format('H:i:s'),
        ];
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

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
