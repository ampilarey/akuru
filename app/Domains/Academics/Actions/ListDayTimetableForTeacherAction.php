<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Models\CalendarDay;
use App\Domains\Academics\Models\SubstitutionRequest;
use App\Domains\Academics\Models\Timetable;
use App\Domains\People\Actions\ListTeachersByIdsAction;
use Carbon\Carbon;

/**
 * One school day's periods for a **teacher**, with cover overlays.
 *
 * The mirror of ListDayTimetableForStudentAction, and the one genuinely new
 * read E1's teacher half needs. Reading it off `lesson_logs` instead would be
 * wrong: expected registers are generated in batches, so a day nobody has
 * generated yet would report a free day to someone who is teaching four
 * periods.
 *
 * Cover cuts both ways here, which it does not for a pupil:
 *  - a period this teacher owns but somebody else is covering is still shown,
 *    marked as covered, because they need to know it is handled;
 *  - a period somebody *else* owns that this teacher is covering is theirs for
 *    the day and appears in their list. A cover that does not show up on the
 *    substitute's own timetable is how a class sits unattended.
 *
 * Teacher names come through People's action rather than a Teacher import — the
 * cross-domain baseline may only shrink (rule 3).
 */
class ListDayTimetableForTeacherAction
{
    /**
     * @return array{
     *   date: string,
     *   is_school_day: bool,
     *   note: ?string,
     *   periods: list<array<string, mixed>>
     * }
     */
    public function execute(int $teacherId, string $date): array
    {
        $day = Carbon::parse($date, config('app.timezone'))->startOfDay();
        $dateStr = $day->toDateString();

        $empty = [
            'date' => $dateStr,
            'is_school_day' => true,
            'note' => null,
            'periods' => [],
        ];

        // A calendar day that affects the timetable means no lessons run — the
        // same rule GenerateExpectedRegistersAction uses to skip a date, so the
        // strip cannot promise periods the registers will never create.
        $blocking = CalendarDay::query()
            ->where('affects_timetable', true)
            ->whereDate('date', $dateStr)
            ->first();

        if ($blocking !== null) {
            return [...$empty, 'is_school_day' => false, 'note' => (string) $blocking->title];
        }

        $covering = $this->coveringEntryIds($teacherId, $dateStr);

        $entries = Timetable::query()
            ->where('is_active', true)
            ->where('day_of_week', strtolower($day->englishDayOfWeek))
            ->whereNotNull('period_id')
            ->where(function ($query) use ($teacherId, $covering) {
                $query->where('teacher_id', $teacherId);
                if ($covering !== []) {
                    $query->orWhereIn('id', $covering);
                }
            })
            ->with(['subject', 'period', 'roomRecord', 'classRoom'])
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

        $covers = $this->coverByTimetableEntry($entries->pluck('id')->all(), $dateStr);

        $names = app(ListTeachersByIdsAction::class)
            ->execute($entries->pluck('teacher_id')->merge($covers->pluck('substitute_teacher_id'))->all())
            ->keyBy('id');

        $periods = $entries->map(function (Timetable $entry) use ($covers, $names, $teacherId): array {
            $cover = $covers->get($entry->id);
            $substituteId = $cover['substitute_teacher_id'] ?? null;
            $isMine = (int) $entry->teacher_id === $teacherId;

            return [
                'timetable_entry_id' => (int) $entry->id,
                'period_id' => (int) $entry->period_id,
                'period_name' => (string) ($entry->period?->name ?? ''),
                'starts_at' => $entry->start_time ? Carbon::parse($entry->start_time)->format('H:i') : null,
                'ends_at' => $entry->end_time ? Carbon::parse($entry->end_time)->format('H:i') : null,
                'subject' => (string) ($entry->subject?->name ?? ''),
                'class' => (string) ($entry->classRoom?->name ?? ''),
                // Timetable carries both a room_id relation and a free-text
                // `room` string; prefer the record, fall back to the string.
                'room' => $entry->roomRecord?->name ?: ($entry->room ?: null),
                // Said plainly rather than left for the reader to work out from
                // two other fields: this is the line a teacher scans at 07:30.
                'is_mine' => $isMine,
                'is_covering_for' => $isMine ? null : ($names->get((int) $entry->teacher_id)['name'] ?? null),
                'is_substituted' => $isMine && $cover !== null,
                'substitute_teacher' => $isMine && $substituteId
                    ? ($names->get((int) $substituteId)['name'] ?? null)
                    : null,
                'cover_status' => $cover['status'] ?? null,
            ];
        })->all();

        return [...$empty, 'periods' => $periods];
    }

    /**
     * Entries this teacher is the assigned substitute for on the day.
     *
     * Only assigned cover counts. An open request means nobody has agreed yet,
     * and putting an unassigned period on someone's timetable would tell them
     * they are teaching a class they have not been given.
     *
     * @return list<int>
     */
    private function coveringEntryIds(int $teacherId, string $dateStr): array
    {
        return SubstitutionRequest::query()
            ->whereDate('date', $dateStr)
            ->whereNotNull('timetable_entry_id')
            ->whereHas('assignment', fn ($query) => $query->where('substitute_teacher_id', $teacherId))
            ->pluck('timetable_entry_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $entryIds
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function coverByTimetableEntry(array $entryIds, string $dateStr): \Illuminate\Support\Collection
    {
        if ($entryIds === []) {
            return collect();
        }

        return SubstitutionRequest::query()
            ->whereIn('timetable_entry_id', $entryIds)
            ->whereDate('date', $dateStr)
            ->with('assignment')
            ->get()
            ->keyBy('timetable_entry_id')
            ->map(fn (SubstitutionRequest $request): array => [
                'status' => (string) $request->status,
                'substitute_teacher_id' => $request->assignment?->substitute_teacher_id,
            ]);
    }
}
