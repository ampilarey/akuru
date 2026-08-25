<?php

namespace App\Domains\Academics\Services;

/**
 * Pure room-booking clash engine. No Eloquent.
 *
 * Booking arrays: id, room_id, academic_year_id, date, day_of_week,
 * period_id, start_time, end_time.
 *
 * Timetable arrays match TimetableConflictChecker entries.
 *
 * @phpstan-type Booking array{
 *     id?: int|null,
 *     room_id: int,
 *     academic_year_id: int,
 *     date: string,
 *     day_of_week: string,
 *     period_id?: int|null,
 *     start_time?: string|null,
 *     end_time?: string|null
 * }
 * @phpstan-type Slot array{
 *     id?: int|null,
 *     room_id?: int|null,
 *     academic_year_id: int,
 *     day_of_week: string,
 *     period_id?: int|null,
 *     start_time?: string|null,
 *     end_time?: string|null,
 *     valid_from?: string|null,
 *     valid_until?: string|null,
 *     is_active?: bool
 * }
 */
class RoomBookingClashChecker
{
    /**
     * @param  Booking  $proposed
     * @param  list<Booking>  $existingBookings
     * @param  list<Slot>  $timetableEntries
     * @param  array<int, array{start: string, end: string}>  $periodTimes
     * @return list<array{type: string, id: int}>
     */
    public function checkBooking(array $proposed, array $existingBookings, array $timetableEntries, array $periodTimes = []): array
    {
        $proposedTimes = $this->resolveTimes($proposed, $periodTimes);
        if ($proposedTimes === null) {
            return [];
        }

        $conflicts = [];
        $proposedId = (int) ($proposed['id'] ?? 0);

        foreach ($existingBookings as $booking) {
            $id = (int) ($booking['id'] ?? 0);
            if ($id > 0 && $id === $proposedId) {
                continue;
            }

            if ((int) $booking['academic_year_id'] !== (int) $proposed['academic_year_id']) {
                continue;
            }

            if ((int) $booking['room_id'] !== (int) $proposed['room_id']) {
                continue;
            }

            if ((string) $booking['date'] !== (string) $proposed['date']) {
                continue;
            }

            $otherTimes = $this->resolveTimes($booking, $periodTimes);
            if ($otherTimes === null || ! $this->timesOverlap($proposedTimes, $otherTimes)) {
                continue;
            }

            if ($id > 0) {
                $conflicts[] = ['type' => 'booking', 'id' => $id];
            }
        }

        foreach ($timetableEntries as $entry) {
            if (($entry['is_active'] ?? true) === false) {
                continue;
            }

            $entryRoom = $entry['room_id'] ?? null;
            if ($entryRoom === null || (int) $entryRoom !== (int) $proposed['room_id']) {
                continue;
            }

            if ((int) $entry['academic_year_id'] !== (int) $proposed['academic_year_id']) {
                continue;
            }

            if (strtolower((string) $entry['day_of_week']) !== strtolower((string) $proposed['day_of_week'])) {
                continue;
            }

            if (! $this->dateInsideWindow(
                (string) $proposed['date'],
                $entry['valid_from'] ?? null,
                $entry['valid_until'] ?? null,
            )) {
                continue;
            }

            $otherTimes = $this->resolveTimes($entry, $periodTimes);
            if ($otherTimes === null || ! $this->timesOverlap($proposedTimes, $otherTimes)) {
                continue;
            }

            $entryId = (int) ($entry['id'] ?? 0);
            if ($entryId > 0) {
                $conflicts[] = ['type' => 'timetable', 'id' => $entryId];
            }
        }

        return $conflicts;
    }

    /**
     * @param  Slot  $proposed
     * @param  list<Booking>  $bookings
     * @param  array<int, array{start: string, end: string}>  $periodTimes
     * @return list<array{type: string, id: int}>
     */
    public function checkTimetable(array $proposed, array $bookings, array $periodTimes = []): array
    {
        $roomId = $proposed['room_id'] ?? null;
        if ($roomId === null || $roomId === '') {
            return [];
        }

        $proposedTimes = $this->resolveTimes($proposed, $periodTimes);
        if ($proposedTimes === null) {
            return [];
        }

        $conflicts = [];

        foreach ($bookings as $booking) {
            if ((int) $booking['academic_year_id'] !== (int) $proposed['academic_year_id']) {
                continue;
            }

            if ((int) $booking['room_id'] !== (int) $roomId) {
                continue;
            }

            if (strtolower((string) $booking['day_of_week']) !== strtolower((string) $proposed['day_of_week'])) {
                continue;
            }

            if (! $this->dateInsideWindow(
                (string) $booking['date'],
                $proposed['valid_from'] ?? null,
                $proposed['valid_until'] ?? null,
            )) {
                continue;
            }

            $otherTimes = $this->resolveTimes($booking, $periodTimes);
            if ($otherTimes === null || ! $this->timesOverlap($proposedTimes, $otherTimes)) {
                continue;
            }

            $id = (int) ($booking['id'] ?? 0);
            if ($id > 0) {
                $conflicts[] = ['type' => 'booking', 'id' => $id];
            }
        }

        return $conflicts;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<int, array{start: string, end: string}>  $periodTimes
     * @return array{start: string, end: string}|null
     */
    private function resolveTimes(array $entry, array $periodTimes): ?array
    {
        $periodId = $entry['period_id'] ?? null;

        if ($periodId !== null && $periodId !== '' && isset($periodTimes[(int) $periodId])) {
            return [
                'start' => $this->normalizeTime($periodTimes[(int) $periodId]['start']),
                'end' => $this->normalizeTime($periodTimes[(int) $periodId]['end']),
            ];
        }

        $start = $entry['start_time'] ?? null;
        $end = $entry['end_time'] ?? null;

        if ($start === null || $start === '' || $end === null || $end === '') {
            return null;
        }

        return [
            'start' => $this->normalizeTime((string) $start),
            'end' => $this->normalizeTime((string) $end),
        ];
    }

    /**
     * @param  array{start: string, end: string}  $left
     * @param  array{start: string, end: string}  $right
     */
    private function timesOverlap(array $left, array $right): bool
    {
        return $left['start'] < $right['end'] && $right['start'] < $left['end'];
    }

    private function dateInsideWindow(string $date, mixed $from, mixed $until): bool
    {
        $start = ($from === null || $from === '') ? '0001-01-01' : (string) $from;
        $end = ($until === null || $until === '') ? '9999-12-31' : (string) $until;

        return $date >= $start && $date <= $end;
    }

    private function normalizeTime(string $time): string
    {
        $time = trim($time);

        if (strlen($time) === 5) {
            return $time.':00';
        }

        return $time;
    }
}
