<?php

namespace App\Domains\Academics\Services;

/**
 * Pure conflict engine. No Eloquent. Unit-tested.
 *
 * Proposed and existing entries are arrays with:
 * id, class_id, teacher_id, room_id, day_of_week, period_id,
 * start_time, end_time, academic_year_id, term_id, valid_from,
 * valid_until, is_active.
 *
 * $periodTimes maps period_id => ['start' => 'H:i[:s]', 'end' => 'H:i[:s]'].
 *
 * @phpstan-type Entry array{
 *     id?: int|null,
 *     class_id: int,
 *     teacher_id: int,
 *     room_id?: int|null,
 *     day_of_week: string,
 *     period_id?: int|null,
 *     start_time?: string|null,
 *     end_time?: string|null,
 *     academic_year_id: int,
 *     term_id?: int|null,
 *     valid_from?: string|null,
 *     valid_until?: string|null,
 *     is_active?: bool
 * }
 */
class TimetableConflictChecker
{
    /**
     * @param  Entry  $proposed
     * @param  list<Entry>  $existing
     * @param  array<int, array{start: string, end: string}>  $periodTimes
     * @return list<array{type: string, timetable_id: int}>
     */
    public function check(array $proposed, array $existing, array $periodTimes = []): array
    {
        $proposedTimes = $this->resolveTimes($proposed, $periodTimes);
        if ($proposedTimes === null) {
            return [];
        }

        $conflicts = [];

        foreach ($existing as $entry) {
            if (($entry['is_active'] ?? true) === false) {
                continue;
            }

            $existingId = isset($entry['id']) ? (int) $entry['id'] : 0;
            if ($existingId > 0 && $existingId === (int) ($proposed['id'] ?? 0)) {
                continue;
            }

            if ((int) $entry['academic_year_id'] !== (int) $proposed['academic_year_id']) {
                continue;
            }

            if (! $this->sameDay($proposed['day_of_week'], $entry['day_of_week'])) {
                continue;
            }

            if (! $this->termsOverlap($proposed['term_id'] ?? null, $entry['term_id'] ?? null)) {
                continue;
            }

            if (! $this->windowsOverlap(
                $proposed['valid_from'] ?? null,
                $proposed['valid_until'] ?? null,
                $entry['valid_from'] ?? null,
                $entry['valid_until'] ?? null,
            )) {
                continue;
            }

            $otherTimes = $this->resolveTimes($entry, $periodTimes);
            if ($otherTimes === null || ! $this->timesOverlap($proposedTimes, $otherTimes)) {
                continue;
            }

            if ((int) $proposed['teacher_id'] === (int) $entry['teacher_id'] && $existingId > 0) {
                $conflicts[] = ['type' => 'teacher', 'timetable_id' => $existingId];
            }

            $proposedRoom = $proposed['room_id'] ?? null;
            $entryRoom = $entry['room_id'] ?? null;
            if ($proposedRoom !== null && $entryRoom !== null
                && (int) $proposedRoom === (int) $entryRoom && $existingId > 0) {
                $conflicts[] = ['type' => 'room', 'timetable_id' => $existingId];
            }

            if ((int) $proposed['class_id'] === (int) $entry['class_id'] && $existingId > 0) {
                $conflicts[] = ['type' => 'class', 'timetable_id' => $existingId];
            }
        }

        return $conflicts;
    }

    private function sameDay(string $left, string $right): bool
    {
        return strtolower($left) === strtolower($right);
    }

    private function termsOverlap(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return true;
        }

        return (int) $left === (int) $right;
    }

    private function windowsOverlap(mixed $aFrom, mixed $aUntil, mixed $bFrom, mixed $bUntil): bool
    {
        $aStart = $this->dateOrUnbounded($aFrom, '0001-01-01');
        $aEnd = $this->dateOrUnbounded($aUntil, '9999-12-31');
        $bStart = $this->dateOrUnbounded($bFrom, '0001-01-01');
        $bEnd = $this->dateOrUnbounded($bUntil, '9999-12-31');

        return $aStart <= $bEnd && $bStart <= $aEnd;
    }

    private function dateOrUnbounded(mixed $value, string $fallback): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return (string) $value;
    }

    /**
     * @param  Entry  $entry
     * @param  array<int, array{start: string, end: string}>  $periodTimes
     * @return array{start: string, end: string}|null
     */
    private function resolveTimes(array $entry, array $periodTimes): ?array
    {
        $periodId = $entry['period_id'] ?? null;

        if ($periodId !== null && isset($periodTimes[(int) $periodId])) {
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

    private function normalizeTime(string $time): string
    {
        $time = trim($time);

        if (strlen($time) === 5) {
            return $time.':00';
        }

        return $time;
    }
}
