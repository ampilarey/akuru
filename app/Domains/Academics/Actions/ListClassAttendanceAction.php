<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\ClassAttendance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListClassAttendanceAction
{
    /**
     * @param  array{academic_year_id?: int|null, class_id?: int|null, student_id?: int|null, from?: string|null, to?: string|null, status?: string|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $rows = ClassAttendance::query()
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['class_id'] ?? null, fn ($query, $id) => $query->where('class_id', $id))
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('student_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('date', '<=', $to))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('date')
            ->orderBy('period_id')
            ->get();

        return $this->serialize($rows);
    }

    /**
     * @param  Collection<int, ClassAttendance>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function serialize(Collection $rows): Collection
    {
        $students = DB::table('students')
            ->whereIn('id', $rows->pluck('student_id')->unique())
            ->get(['id', 'first_name', 'last_name', 'student_id'])
            ->keyBy('id');
        $classes = DB::table('classes')
            ->whereIn('id', $rows->pluck('class_id')->unique())
            ->get(['id', 'name', 'section'])
            ->keyBy('id');

        return $rows->map(function (ClassAttendance $row) use ($students, $classes) {
            $student = $students[$row->student_id] ?? null;
            $class = $classes[$row->class_id] ?? null;

            return [
                'id' => $row->id,
                'student_id' => $row->student_id,
                'student_name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                'student_number' => $student->student_id ?? null,
                'class_id' => $row->class_id,
                'class_name' => trim(($class->name ?? '').' '.($class->section ?? '')),
                'date' => $row->date?->toDateString(),
                'period_id' => $row->period_id,
                'status' => $row->status?->value,
                'minutes_late' => $row->minutes_late,
                'source' => $row->source?->value,
                'remarks' => $row->remarks,
            ];
        })->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function studentSummary(int $studentId, ?int $academicYearId = null): Collection
    {
        $query = ClassAttendance::query()->where('student_id', $studentId);
        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        $total = (clone $query)->count();
        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $present = (int) ($byStatus[AttendanceStatus::Present->value] ?? 0);
        $late = (int) ($byStatus[AttendanceStatus::Late->value] ?? 0);

        return collect([[
            'student_id' => $studentId,
            'total' => $total,
            'present' => $present,
            'late' => $late,
            'absent' => (int) ($byStatus[AttendanceStatus::Absent->value] ?? 0),
            'excused' => (int) ($byStatus[AttendanceStatus::Excused->value] ?? 0),
            'left_early' => (int) ($byStatus[AttendanceStatus::LeftEarly->value] ?? 0),
            'percent' => $total === 0 ? 0 : round((($present + $late) / $total) * 100, 1),
        ]]);
    }

    /**
     * Distinct unexcused absent dates at or above the chronic threshold.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function chronic(?int $academicYearId = null, ?int $threshold = null): Collection
    {
        $threshold ??= app(ResolveAttendanceSettingsAction::class)->execute()['chronic_threshold'];

        $rows = ClassAttendance::query()
            ->when($academicYearId, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->where('status', AttendanceStatus::Absent->value)
            ->selectRaw('student_id, COUNT(DISTINCT date) as absent_days')
            ->groupBy('student_id')
            ->having('absent_days', '>=', $threshold)
            ->get();

        $students = DB::table('students')
            ->whereIn('id', $rows->pluck('student_id'))
            ->get(['id', 'first_name', 'last_name', 'student_id'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($students) {
            $student = $students[$row->student_id] ?? null;

            return [
                'student_id' => (int) $row->student_id,
                'student_name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                'student_number' => $student->student_id ?? null,
                'absent_days' => (int) $row->absent_days,
            ];
        })->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function unexcused(?int $academicYearId = null): Collection
    {
        return $this->execute([
            'academic_year_id' => $academicYearId,
            'status' => AttendanceStatus::Absent->value,
        ]);
    }
}
