<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\ClassStudentStatus;
use App\Domains\Academics\Models\CalendarDay;
use App\Domains\Academics\Models\ClassStudent;
use App\Domains\Academics\Models\SubstitutionRequest;
use App\Domains\Academics\Models\Timetable;
use App\Domains\People\Actions\ListTeachersByIdsAction;
use Carbon\Carbon;

/**
 * One school day's periods for a student's class, with substitution overlays.
 *
 * Built for the portal home's "tomorrow" strip (E1) and reused by the homework
 * slice (E3) to default a due date to the class's next lesson. Nothing else in
 * Academics reads a day's timetable for a student — Save, Copy,
 * PreviewConflicts, Backfill and Sync all write or validate.
 *
 * Teacher names come through People's ListTeachersByIdsAction rather than
 * importing People\Models\Teacher: the cross-domain baseline may only shrink
 * (rule 3), so a new file must not add a violation.
 */
class ListDayTimetableForStudentAction
{
    /**
     * @return array{
     *   date: string,
     *   is_school_day: bool,
     *   note: ?string,
     *   class: ?array{id: int, name: string},
     *   periods: list<array<string, mixed>>
     * }
     */
    public function execute(int $studentId, string $date): array
    {
        $day = Carbon::parse($date, config('app.timezone'))->startOfDay();
        $dateStr = $day->toDateString();

        $empty = [
            'date' => $dateStr,
            'is_school_day' => true,
            'note' => null,
            'class' => null,
            'periods' => [],
        ];

        $roster = ClassStudent::query()
            ->where('student_id', $studentId)
            ->where('status', ClassStudentStatus::Active->value)
            ->with('classRoom')
            ->first();

        if ($roster === null || $roster->classRoom === null) {
            return $empty;
        }

        $class = $roster->classRoom;
        $empty['class'] = ['id' => (int) $class->id, 'name' => (string) $class->name];

        // A calendar day that affects the timetable means no lessons run —
        // the same rule GenerateExpectedRegistersAction uses to skip a date,
        // so the strip cannot promise periods the registers will never create.
        $blocking = CalendarDay::query()
            ->where('academic_year_id', $class->academic_year_id)
            ->where('affects_timetable', true)
            ->whereDate('date', $dateStr)
            ->first();

        if ($blocking !== null) {
            return [...$empty, 'is_school_day' => false, 'note' => (string) $blocking->title];
        }

        $entries = Timetable::query()
            ->where('academic_year_id', $class->academic_year_id)
            ->where('class_id', $class->id)
            ->where('is_active', true)
            ->where('day_of_week', strtolower($day->englishDayOfWeek))
            ->whereNotNull('period_id')
            ->with(['subject', 'period', 'roomRecord'])
            ->get()
            ->filter(fn (Timetable $entry): bool => ! (
                ($entry->valid_from && $day->lt($entry->valid_from))
                || ($entry->valid_until && $day->gt($entry->valid_until))
            ))
            ->sortBy(fn (Timetable $entry) => [$entry->period?->order ?? 0, (string) $entry->start_time])
            ->values();

        if ($entries->isEmpty()) {
            return $empty;
        }

        $covers = $this->coverByTimetableEntry((int) $class->id, $dateStr);

        $teacherIds = $entries->pluck('teacher_id')
            ->merge($covers->pluck('substitute_teacher_id'))
            ->all();
        $names = app(ListTeachersByIdsAction::class)->execute($teacherIds)->keyBy('id');

        $periods = $entries->map(function (Timetable $entry) use ($covers, $names): array {
            $cover = $covers->get($entry->id);
            $substituteId = $cover['substitute_teacher_id'] ?? null;

            return [
                'timetable_entry_id' => (int) $entry->id,
                'period_id' => (int) $entry->period_id,
                'period_name' => (string) ($entry->period?->name ?? ''),
                'starts_at' => $entry->start_time ? Carbon::parse($entry->start_time)->format('H:i') : null,
                'ends_at' => $entry->end_time ? Carbon::parse($entry->end_time)->format('H:i') : null,
                'subject' => (string) ($entry->subject?->name ?? ''),
                // Timetable carries both a room_id relation and a free-text
                // `room` string; prefer the record, fall back to the string.
                'room' => $entry->roomRecord?->name ?: ($entry->room ?: null),
                'teacher' => $names->get((int) $entry->teacher_id)['name'] ?? null,
                // A period is only "covered" once a substitute is assigned; an
                // open request means the class is unstaffed, which the strip
                // should say plainly rather than showing the absent teacher.
                'is_substituted' => $cover !== null,
                'substitute_teacher' => $substituteId
                    ? ($names->get((int) $substituteId)['name'] ?? null)
                    : null,
                'cover_status' => $cover['status'] ?? null,
            ];
        })->all();

        return [...$empty, 'periods' => $periods];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function coverByTimetableEntry(int $classId, string $dateStr): \Illuminate\Support\Collection
    {
        return SubstitutionRequest::query()
            ->where('classroom_id', $classId)
            ->whereDate('date', $dateStr)
            ->whereNotNull('timetable_entry_id')
            ->with('assignment')
            ->get()
            ->keyBy('timetable_entry_id')
            ->map(fn (SubstitutionRequest $request): array => [
                'status' => (string) $request->status,
                'substitute_teacher_id' => $request->assignment?->substitute_teacher_id,
            ]);
    }
}
