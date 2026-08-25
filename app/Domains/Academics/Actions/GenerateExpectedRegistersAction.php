<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\CalendarDay;
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
    public function execute(?int $academicYearId = null, ?string $from = null, ?string $to = null): array
    {
        $year = $this->year($academicYearId);
        if ($year === null) {
            return ['created' => 0, 'skipped' => 0];
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
            return ['created' => 0, 'skipped' => 0];
        }

        $blocked = CalendarDay::query()
            ->where('academic_year_id', $year->id)
            ->where('affects_timetable', true)
            ->whereDate('date', '>=', $fromDate->toDateString())
            ->whereDate('date', '<=', $toDate->toDateString())
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $entries = Timetable::query()
            ->where('academic_year_id', $year->id)
            ->where('is_active', true)
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

        return ['created' => $created, 'skipped' => $skipped];
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
