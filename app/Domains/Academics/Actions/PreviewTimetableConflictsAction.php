<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\Period;
use App\Domains\Academics\Models\Timetable;
use App\Domains\Academics\Services\TimetableConflictChecker;

class PreviewTimetableConflictsAction
{
    public function __construct(private TimetableConflictChecker $checker) {}

    /**
     * @param  array<string, mixed>  $proposed
     * @return list<array{type: string, timetable_id: int}>
     */
    public function execute(array $proposed): array
    {
        $yearId = (int) $proposed['academic_year_id'];
        $day = strtolower((string) $proposed['day_of_week']);

        return $this->checker->check(
            [
                'id' => isset($proposed['id']) ? (int) $proposed['id'] : null,
                'class_id' => (int) $proposed['class_id'],
                'teacher_id' => (int) $proposed['teacher_id'],
                'room_id' => isset($proposed['room_id']) && $proposed['room_id'] !== ''
                    ? (int) $proposed['room_id']
                    : null,
                'day_of_week' => $day,
                'period_id' => isset($proposed['period_id']) && $proposed['period_id'] !== ''
                    ? (int) $proposed['period_id']
                    : null,
                'start_time' => $proposed['start_time'] ?? null,
                'end_time' => $proposed['end_time'] ?? null,
                'academic_year_id' => $yearId,
                'term_id' => isset($proposed['term_id']) && $proposed['term_id'] !== ''
                    ? (int) $proposed['term_id']
                    : null,
                'valid_from' => $proposed['valid_from'] ?? null,
                'valid_until' => $proposed['valid_until'] ?? null,
                'is_active' => true,
            ],
            $this->existing($yearId, $day),
            $this->periodTimes(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function existing(int $yearId, string $day): array
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
