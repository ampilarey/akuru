<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\CalendarDay;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\LessonLog;
use App\Domains\Academics\Models\Timetable;
use Carbon\Carbon;

class GenerateExpectedRegistersAction
{
    /**
     * Create expected lesson_logs for timetable slots on school days.
     * Skips calendar days with affects_timetable=true. Idempotent.
     *
     * @return array{created: int, skipped: int}
     */
    public function execute(
        ?int $academicYearId = null,
        ?string $from = null,
        ?string $to = null,
        ?int $teacherId = null,
        ?int $classTeacherUserId = null,
    ): array {
        $year = $this->year($academicYearId);
        if ($year === null) {
            return $this->outcome(0, 0, 'Created 0 expected registers (no academic year).');
        }

        $fromDate = Carbon::parse($from ?? now()->toDateString(), config('app.timezone'))->startOfDay();
        $toDate = Carbon::parse($to ?? now()->toDateString(), config('app.timezone'))->startOfDay();

        if ($fromDate->lt($year->start_date)) {
            $fromDate = $year->start_date->copy()->startOfDay();
        }
        if ($toDate->gt($year->end_date)) {
            $toDate = $year->end_date->copy()->startOfDay();
        }
        if ($fromDate->gt($toDate)) {
            return $this->outcome(0, 0, 'Created 0 expected registers (date range is outside the academic year).');
        }

        $blocked = CalendarDay::query()
            ->where('academic_year_id', $year->id)
            ->where('affects_timetable', true)
            ->whereDate('date', '>=', $fromDate->toDateString())
            ->whereDate('date', '<=', $toDate->toDateString())
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $classIds = [];
        if ($classTeacherUserId) {
            $classIds = ClassRoom::query()
                ->where('class_teacher_id', $classTeacherUserId)
                ->pluck('id')
                ->all();
        }

        $entries = Timetable::query()
            ->where('academic_year_id', $year->id)
            ->where('is_active', true)
            ->when($teacherId || $classIds !== [], function ($query) use ($teacherId, $classIds) {
                $query->where(function ($inner) use ($teacherId, $classIds) {
                    if ($teacherId) {
                        $inner->where('teacher_id', $teacherId);
                    }
                    if ($classIds !== []) {
                        $inner->orWhereIn('class_id', $classIds);
                    }
                });
            })
            ->get();

        $created = 0;
        $skipped = 0;

        for ($date = $fromDate->copy(); $date->lte($toDate); $date->addDay()) {
            $dateStr = $date->toDateString();
            if (in_array($dateStr, $blocked, true)) {
                continue;
            }

            $weekday = strtolower($date->englishDayOfWeek);

            foreach ($entries as $entry) {
                if (strtolower((string) $entry->day_of_week) !== $weekday) {
                    continue;
                }
                if (! $this->validOn($entry, $date)) {
                    continue;
                }
                if ($this->alreadyExists($entry, $dateStr)) {
                    $skipped++;

                    continue;
                }

                LessonLog::query()->create([
                    'teacher_id' => $entry->teacher_id,
                    'subject_id' => $entry->subject_id,
                    'classroom_id' => $entry->class_id,
                    'academic_year_id' => $year->id,
                    'term_id' => $entry->term_id,
                    'timetable_id' => $entry->id,
                    'date' => $dateStr,
                    'period_id' => $entry->period_id,
                    'status' => LessonLogStatus::Expected,
                    'taught_summary' => null,
                ]);
                $created++;
            }
        }

        return $this->outcome($created, $skipped);
    }

    /**
     * @return array{created: int, skipped: int, message: string}
     */
    private function outcome(int $created, int $skipped, ?string $message = null): array
    {
        if ($message === null) {
            if ($created === 0 && $skipped > 0) {
                $message = "Created 0 expected registers ({$skipped} already exist).";
            } elseif ($created === 0) {
                $message = 'Created 0 expected registers (no matching timetable slots on school days in this range).';
            } elseif ($skipped > 0) {
                $message = "Created {$created} expected registers ({$skipped} already exist).";
            } else {
                $message = "Created {$created} expected registers.";
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'message' => $message,
        ];
    }

    private function year(?int $academicYearId): ?AcademicYear
    {
        if ($academicYearId) {
            return AcademicYear::query()->find($academicYearId);
        }

        return AcademicYear::query()->where('status', AcademicYearStatus::Active)->first()
            ?? AcademicYear::query()->where('is_current', true)->first();
    }

    private function validOn(Timetable $entry, Carbon $date): bool
    {
        $from = $entry->valid_from ?? $entry->start_date;
        $until = $entry->valid_until ?? $entry->end_date;

        if ($from && $date->lt(Carbon::parse($from)->startOfDay())) {
            return false;
        }

        if ($until && $date->gt(Carbon::parse($until)->startOfDay())) {
            return false;
        }

        return true;
    }

    private function alreadyExists(Timetable $entry, string $date): bool
    {
        if (LessonLog::query()->where('timetable_id', $entry->id)->whereDate('date', $date)->exists()) {
            return true;
        }

        $query = LessonLog::query()
            ->where('teacher_id', $entry->teacher_id)
            ->where('subject_id', $entry->subject_id)
            ->where('classroom_id', $entry->class_id)
            ->whereDate('date', $date);

        if ($entry->period_id === null) {
            return $query->whereNull('period_id')->exists();
        }

        return $query->where('period_id', $entry->period_id)->exists();
    }
}
