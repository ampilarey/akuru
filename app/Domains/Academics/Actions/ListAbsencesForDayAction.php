<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\AbsenceNote;
use App\Domains\Academics\Models\ClassAttendance;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who is not in today, and whether anybody knows why.
 *
 * The question a school office asks at 08:30 and could not ask here: attendance
 * is recorded per lesson and absence notes are reviewed in a separate queue,
 * so answering "which children are missing and which of those are unexplained"
 * meant reading two screens and doing the join in your head.
 *
 * The join is the feature. An absence with a note is administration; an absence
 * with **no** note is the one to telephone a parent about, and it is the one
 * neither existing screen makes visible.
 *
 * Marks are per lesson, so a child absent for four periods is one absent child,
 * not four. Collapsing that is the difference between a list you act on and a
 * list you scroll.
 */
class ListAbsencesForDayAction
{
    /** Marks that mean the child is not sitting in the lesson. */
    private const MISSING = [
        AttendanceStatus::Absent->value,
        AttendanceStatus::Excused->value,
    ];

    /**
     * @param  array{date?: ?string, class_id?: ?int, only_unexplained?: bool}  $filters
     * @return array{date: string, students: list<array<string, mixed>>, counts: array<string, int>}
     */
    public function execute(array $filters = []): array
    {
        $date = ($filters['date'] ?? null) ?: Carbon::now(config('app.timezone'))->toDateString();

        $marks = ClassAttendance::query()
            ->whereDate('date', $date)
            ->whereIn('status', self::MISSING)
            ->when($filters['class_id'] ?? null, fn ($query, $id) => $query->where('class_id', $id))
            ->get();

        if ($marks->isEmpty()) {
            return ['date' => $date, 'students' => [], 'counts' => $this->counts(collect())];
        }

        $studentIds = $marks->pluck('student_id')->unique()->values();

        $students = DB::table('students')
            ->whereIn('id', $studentIds)
            ->get(['id', 'first_name', 'last_name', 'student_id'])
            ->keyBy('id');
        $classes = DB::table('classes')
            ->whereIn('id', $marks->pluck('class_id')->unique())
            ->get(['id', 'name', 'section'])
            ->keyBy('id');
        $periods = DB::table('periods')
            ->whereIn('id', $marks->pluck('period_id')->filter()->unique())
            ->get(['id', 'name', 'order'])
            ->keyBy('id');

        $notes = $this->notesFor($studentIds->all(), $date);

        $rows = $marks
            ->groupBy('student_id')
            ->map(function (Collection $studentMarks, $studentId) use ($students, $classes, $periods, $notes): array {
                $first = $studentMarks->first();
                $student = $students[$studentId] ?? null;
                $class = $classes[$first->class_id] ?? null;
                $note = $notes->get((int) $studentId);

                return [
                    'student_id' => (int) $studentId,
                    'student_name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
                    'student_number' => $student->student_id ?? null,
                    'class_id' => (int) $first->class_id,
                    'class_name' => trim(($class->name ?? '').' '.($class->section ?? '')),
                    // One child, however many periods they missed.
                    'periods_missed' => $studentMarks->count(),
                    'periods' => $studentMarks
                        ->sortBy(fn (ClassAttendance $mark) => $periods[$mark->period_id]->order ?? 0)
                        ->map(fn (ClassAttendance $mark): string => (string) ($periods[$mark->period_id]->name ?? '—'))
                        ->values()
                        ->all(),
                    // Absent all day reads differently from one period missed,
                    // and the office treats them differently.
                    'statuses' => $studentMarks->pluck('status')
                        ->map(fn ($status): string => $status?->value ?? '')
                        ->unique()->values()->all(),
                    'note_status' => $note['status'] ?? null,
                    'note_reason' => $note['reason'] ?? null,
                    // The whole point of the join.
                    'is_unexplained' => $note === null,
                ];
            })
            ->values();

        if ($filters['only_unexplained'] ?? false) {
            $rows = $rows->filter(fn (array $row): bool => $row['is_unexplained'])->values();
        }

        return [
            'date' => $date,
            // Unexplained first: the list is a call sheet, not a report.
            'students' => $rows
                ->sortBy([
                    fn (array $a, array $b): int => ($a['is_unexplained'] ? 0 : 1) <=> ($b['is_unexplained'] ? 0 : 1),
                    fn (array $a, array $b): int => strcmp($a['class_name'], $b['class_name']),
                    fn (array $a, array $b): int => strcmp($a['student_name'], $b['student_name']),
                ])
                ->values()
                ->all(),
            'counts' => $this->counts($rows),
        ];
    }

    /**
     * The note covering each student on the day, if any.
     *
     * A rejected note does not explain an absence — that is what rejecting it
     * meant — so it is not counted as cover. A pending one does explain it:
     * somebody told the school, and the office should not be telephoning them
     * while it waits to be reviewed.
     *
     * @param  list<int>  $studentIds
     * @return Collection<int, array<string, mixed>>
     */
    private function notesFor(array $studentIds, string $date): Collection
    {
        return AbsenceNote::query()
            ->whereIn('student_id', $studentIds)
            ->whereDate('date', $date)
            ->where('status', '!=', 'rejected')
            ->get()
            ->groupBy('student_id')
            ->map(fn (Collection $notes): array => [
                // An approved note outranks a pending one where both exist.
                'status' => (string) ($notes->firstWhere('status', 'approved')?->status
                    ?? $notes->first()->status),
                'reason' => (string) ($notes->firstWhere('status', 'approved')?->reason
                    ?? $notes->first()->reason),
            ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function counts(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'unexplained' => $rows->filter(fn (array $row): bool => $row['is_unexplained'])->count(),
        ];
    }
}
