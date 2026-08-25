<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Exceptions\TimetableConflictException;
use App\Domains\Academics\Models\Timetable;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CopyTimetableEntriesAction
{
    public function __construct(private SaveTimetableEntryAction $save) {}

    /**
     * @return array{copied: int, skipped: int}
     */
    public function fromClass(int $sourceClassId, int $targetClassId, int $yearId): array
    {
        if ($sourceClassId === $targetClassId) {
            throw ValidationException::withMessages([
                'source_class_id' => 'Choose a different class to copy from.',
            ]);
        }

        return $this->copy(
            Timetable::query()
                ->where('academic_year_id', $yearId)
                ->where('class_id', $sourceClassId)
                ->where('is_active', true)
                ->get(),
            ['class_id' => $targetClassId],
            0,
            true,
        );
    }

    /**
     * Clone a class week with validity shifted by 7 days.
     * Unbounded source rows are first scoped to the current week so the
     * copies do not collide with open-ended originals.
     *
     * @return array{copied: int, skipped: int}
     */
    public function shiftWeek(int $classId, int $yearId, ?string $weekStart = null): array
    {
        $anchor = Carbon::parse($weekStart ?? now()->startOfWeek()->toDateString())->startOfDay();

        $rows = Timetable::query()
            ->where('academic_year_id', $yearId)
            ->where('class_id', $classId)
            ->where('is_active', true)
            ->get();

        foreach ($rows as $row) {
            if ($row->valid_from === null) {
                $row->valid_from = $anchor->toDateString();
                $row->valid_until = $anchor->copy()->addDays(6)->toDateString();
                $row->save();
            }
        }

        return $this->copy($rows, [], 7);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Timetable>  $rows
     * @param  array<string, mixed>  $overrides
     * @return array{copied: int, skipped: int}
     */
    private function copy($rows, array $overrides, int $shiftDays = 0, bool $allowConflict = false): array
    {
        $copied = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $from = $row->valid_from?->toDateString();
            $until = $row->valid_until?->toDateString();

            if ($shiftDays !== 0) {
                $fromCarbon = Carbon::parse($from ?? now()->toDateString())->addDays($shiftDays);
                $from = $fromCarbon->toDateString();
                $until = $until
                    ? Carbon::parse($until)->addDays($shiftDays)->toDateString()
                    : $fromCarbon->copy()->addDays(6)->toDateString();
            }

            try {
                $this->save->execute(array_merge([
                    'class_id' => $row->class_id,
                    'subject_id' => $row->subject_id,
                    'teacher_id' => $row->teacher_id,
                    'academic_year_id' => $row->academic_year_id,
                    'term_id' => $row->term_id,
                    'period_id' => $row->period_id,
                    'start_time' => $row->period_id ? null : $row->start_time?->format('H:i:s'),
                    'end_time' => $row->period_id ? null : $row->end_time?->format('H:i:s'),
                    'room_id' => $row->room_id,
                    'day_of_week' => $row->day_of_week,
                    'valid_from' => $from,
                    'valid_until' => $until,
                    'is_active' => true,
                    'allow_conflict' => $allowConflict,
                    'conflict_reason' => $allowConflict ? 'Copied from another class timetable.' : null,
                ], $overrides), null, $allowConflict, null);
                $copied++;
            } catch (TimetableConflictException|ValidationException) {
                $skipped++;
            }
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }
}
