<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Exceptions\TimetableConflictException;
use App\Domains\Academics\Models\Period;
use App\Domains\Academics\Models\Room;
use App\Domains\Academics\Models\RoomBooking;
use App\Domains\Academics\Models\Timetable;
use App\Domains\Academics\Services\RoomBookingClashChecker;
use App\Domains\Academics\Services\TimetableConflictChecker;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SaveTimetableEntryAction
{
    public function __construct(
        private TimetableConflictChecker $checker,
        private RoomBookingClashChecker $bookingChecker,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?Timetable $entry = null, bool $canOverride = false, ?int $actorId = null): Timetable
    {
        $payload = $this->normalized($data, $entry);

        $checkEntry = $this->toCheckEntry($payload, $entry?->id);
        $conflicts = $this->checker->check(
            $checkEntry,
            $this->existingCandidates((int) $payload['academic_year_id'], (string) $payload['day_of_week']),
            $this->periodTimes(),
        );

        foreach ($this->bookingChecker->checkTimetable(
            $checkEntry,
            $this->existingBookings((int) $payload['academic_year_id'], $payload['room_id'] ?? null, (string) $payload['day_of_week']),
            $this->periodTimes(),
        ) as $bookingConflict) {
            $conflicts[] = ['type' => 'booking', 'timetable_id' => $bookingConflict['id']];
        }

        if ($conflicts !== []) {
            $allow = (bool) ($data['allow_conflict'] ?? false);
            $reason = trim((string) ($data['conflict_reason'] ?? ''));

            if (! $allow) {
                throw new TimetableConflictException($conflicts);
            }

            if (! $canOverride) {
                throw ValidationException::withMessages([
                    'allow_conflict' => 'A conflict override permission is required.',
                ]);
            }

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'conflict_reason' => 'A reason is required to save over a conflict.',
                ]);
            }

            Log::info('timetable.conflict_override', [
                'actor_id' => $actorId,
                'timetable_id' => $entry?->id,
                'reason' => $reason,
                'conflicts' => $conflicts,
            ]);
        }

        if ($entry === null) {
            return Timetable::query()->create($payload);
        }

        $entry->fill($payload);
        $entry->save();

        return $entry->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalized(array $data, ?Timetable $entry): array
    {
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

        $roomId = $data['room_id'] ?? null;
        $roomId = $roomId === '' || $roomId === null ? null : (int) $roomId;
        $roomStrings = [];

        if ($roomId !== null) {
            $room = Room::query()->find($roomId);
            if ($room !== null) {
                $roomStrings = [
                    'room' => $room->name,
                    'room_arabic' => $room->name_arabic,
                    'room_dhivehi' => $room->name_dhivehi,
                ];
            }
        }

        return array_merge([
            'class_id' => (int) $data['class_id'],
            'subject_id' => (int) $data['subject_id'],
            'teacher_id' => (int) $data['teacher_id'],
            'academic_year_id' => (int) $data['academic_year_id'],
            'term_id' => isset($data['term_id']) && $data['term_id'] !== '' && $data['term_id'] !== null
                ? (int) $data['term_id']
                : null,
            'period_id' => $periodId,
            'start_time' => $start,
            'end_time' => $end,
            'room_id' => $roomId,
            'day_of_week' => strtolower((string) $data['day_of_week']),
            'valid_from' => $this->nullableString($data['valid_from'] ?? null),
            'valid_until' => $this->nullableString($data['valid_until'] ?? null),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : ($entry?->is_active ?? true),
        ], $roomStrings);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function toCheckEntry(array $payload, ?int $id): array
    {
        return [
            'id' => $id,
            'class_id' => $payload['class_id'],
            'teacher_id' => $payload['teacher_id'],
            'room_id' => $payload['room_id'],
            'day_of_week' => $payload['day_of_week'],
            'period_id' => $payload['period_id'],
            'start_time' => $payload['start_time'],
            'end_time' => $payload['end_time'],
            'academic_year_id' => $payload['academic_year_id'],
            'term_id' => $payload['term_id'],
            'valid_from' => $payload['valid_from'],
            'valid_until' => $payload['valid_until'],
            'is_active' => $payload['is_active'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function existingCandidates(int $yearId, string $day): array
    {
        return Timetable::query()
            ->where('academic_year_id', $yearId)
            ->where('day_of_week', $day)
            ->where('is_active', true)
            ->get()
            ->map(fn (Timetable $row) => [
                'id' => $row->id,
                'class_id' => $row->class_id,
                'teacher_id' => $row->teacher_id,
                'room_id' => $row->room_id,
                'day_of_week' => $row->day_of_week,
                'period_id' => $row->period_id,
                'start_time' => $row->start_time?->format('H:i:s'),
                'end_time' => $row->end_time?->format('H:i:s'),
                'academic_year_id' => $row->academic_year_id,
                'term_id' => $row->term_id,
                'valid_from' => $row->valid_from?->toDateString(),
                'valid_until' => $row->valid_until?->toDateString(),
                'is_active' => $row->is_active,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function existingBookings(int $yearId, mixed $roomId, string $day): array
    {
        if ($roomId === null || $roomId === '') {
            return [];
        }

        return RoomBooking::query()
            ->where('academic_year_id', $yearId)
            ->where('room_id', (int) $roomId)
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
            ->filter(fn (array $row) => $row['day_of_week'] === strtolower($day))
            ->values()
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

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
