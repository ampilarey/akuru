<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\ClassAttendance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * E10 — lateness and early departures, per pupil.
 *
 * `class_attendance.minutes_late` has been written since 2026-08 and never
 * aggregated: a pupil ten minutes late every day for a term shows up nowhere,
 * because `chronic()` counts only full absences.
 *
 * The tardy-to-absence rule (EduPage's documented example is three lates
 * counting as one absence) is reported here **beside** the absence figures,
 * never folded into them. Silently changing what `chronic()` returns would
 * alter a number the school already reads and reports, and would do it without
 * anyone being able to see why it moved. The combined figure is offered as its
 * own column so the school can choose to use it.
 */
class ListTardySummaryAction
{
    /**
     * @param  array{academic_year_id?: int|null, class_id?: int|null, from?: string|null, to?: string|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $perAbsence = app(ResolveAttendanceSettingsAction::class)->execute()['tardies_per_absence'];

        $rows = ClassAttendance::query()
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['class_id'] ?? null, fn ($query, $id) => $query->where('class_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('date', '<=', $to))
            ->whereIn('status', [AttendanceStatus::Late->value, AttendanceStatus::LeftEarly->value])
            ->selectRaw('student_id, status, COUNT(*) as marks, COALESCE(SUM(minutes_late), 0) as minutes')
            ->groupBy('student_id', 'status')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $students = DB::table('students')
            ->whereIn('id', $rows->pluck('student_id')->unique())
            ->get(['id', 'first_name', 'last_name', 'student_id'])
            ->keyBy('id');

        $absences = $this->absenceDaysFor(
            $rows->pluck('student_id')->unique()->map(fn ($id): int => (int) $id)->all(),
            $filters,
        );

        return $rows
            ->groupBy('student_id')
            ->map(function (Collection $group, $studentId) use ($students, $absences, $perAbsence): array {
                $student = $students[$studentId] ?? null;

                $late = $group->firstWhere('status', AttendanceStatus::Late->value);
                $left = $group->firstWhere('status', AttendanceStatus::LeftEarly->value);

                $tardies = (int) ($late->marks ?? 0);
                $absentDays = (int) ($absences[$studentId] ?? 0);

                // Integer division on purpose: two lates under a three-per-rule
                // are not two thirds of an absence, they are not yet an absence.
                $fromTardies = $perAbsence > 0 ? intdiv($tardies, $perAbsence) : 0;

                return [
                    'student_id' => (int) $studentId,
                    'student_name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                    'student_number' => $student->student_id ?? null,
                    'tardies' => $tardies,
                    'minutes_late' => (int) ($late->minutes ?? 0),
                    'early_departures' => (int) ($left->marks ?? 0),
                    'absent_days' => $absentDays,
                    'tardies_per_absence' => $perAbsence,
                    'absences_from_tardies' => $fromTardies,
                    // Offered, not applied. The school reads this column and
                    // decides; nothing downstream consumes it.
                    'effective_absences' => $absentDays + $fromTardies,
                ];
            })
            ->sortByDesc('tardies')
            ->values();
    }

    /**
     * Full-day absences per student over the same window, so the two figures
     * on a row are comparable rather than covering different periods.
     *
     * @param  list<int>  $studentIds
     * @param  array<string, mixed>  $filters
     * @return array<int, int>
     */
    private function absenceDaysFor(array $studentIds, array $filters): array
    {
        if ($studentIds === []) {
            return [];
        }

        return ClassAttendance::query()
            ->whereIn('student_id', $studentIds)
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['class_id'] ?? null, fn ($query, $id) => $query->where('class_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('date', '<=', $to))
            ->where('status', AttendanceStatus::Absent->value)
            ->selectRaw('student_id, COUNT(DISTINCT date) as absent_days')
            ->groupBy('student_id')
            ->pluck('absent_days', 'student_id')
            ->map(fn ($value): int => (int) $value)
            ->all();
    }
}
