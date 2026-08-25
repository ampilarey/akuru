<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\CopyTimetableEntriesAction;
use App\Domains\Academics\Actions\ListActiveTeachersAction;
use App\Domains\Academics\Actions\PreviewTimetableConflictsAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Academics\Exceptions\TimetableConflictException;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\Period;
use App\Domains\Academics\Models\Room;
use App\Domains\Academics\Models\Subject;
use App\Domains\Academics\Models\SubstitutionRequest;
use App\Domains\Academics\Models\TeacherAbsence;
use App\Domains\Academics\Models\Timetable;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimetableBuilderController extends Controller
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('manage_timetables'), 403);

        $yearId = $request->integer('academic_year_id') ?: (int) AcademicYear::query()
            ->where('status', 'active')
            ->value('id');

        $view = $request->string('view')->toString() ?: 'class';
        $classId = $request->integer('class_id') ?: (int) ClassRoom::query()
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
            ->orderBy('name')
            ->value('id');
        $teacherId = $request->integer('teacher_id') ?: null;
        $roomId = $request->integer('room_id') ?: null;

        $entries = Timetable::query()
            ->where('academic_year_id', $yearId)
            ->where('is_active', true)
            ->when($view === 'class' && $classId, fn ($query) => $query->where('class_id', $classId))
            ->when($view === 'teacher' && $teacherId, fn ($query) => $query->where('teacher_id', $teacherId))
            ->when($view === 'room' && $roomId, fn ($query) => $query->where('room_id', $roomId))
            ->orderBy('day_of_week')
            ->get()
            ->map(fn (Timetable $row) => $this->serializeEntry($row));

        $preview = app(PreviewTimetableConflictsAction::class);
        $entries = $entries->map(function (array $entry) use ($preview) {
            $entry['conflicts'] = $preview->execute($entry);

            return $entry;
        });

        return Inertia::render('Academics/Timetable/Builder', [
            'yearId' => $yearId ?: null,
            'view' => $view,
            'classId' => $classId ?: null,
            'teacherId' => $teacherId,
            'roomId' => $roomId,
            'days' => self::DAYS,
            'years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'status']),
            'classes' => ClassRoom::query()
                ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
                ->orderBy('name')
                ->get(['id', 'name', 'section', 'academic_year_id']),
            'periods' => Period::query()->orderBy('order')->get(['id', 'name', 'start_time', 'end_time', 'is_break', 'order']),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'rooms' => Room::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'type']),
            'teachers' => app(ListActiveTeachersAction::class)->execute(),
            'entries' => $entries,
            'substitutions' => $this->substitutions($yearId),
            'canOverride' => (bool) $request->user()?->can('timetables.allow_conflict'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->persist($request);
    }

    public function update(Request $request, Timetable $timetable): RedirectResponse
    {
        return $this->persist($request, $timetable);
    }

    public function destroy(Request $request, Timetable $timetable): RedirectResponse
    {
        abort_unless($request->user()?->can('manage_timetables'), 403);

        $timetable->delete();

        return redirect()
            ->route('academics.timetable.index', $request->only(['academic_year_id', 'view', 'class_id', 'teacher_id', 'room_id']))
            ->with('success', 'Slot removed.');
    }

    public function preview(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('manage_timetables'), 403);

        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'class_id' => ['required', 'integer'],
            'teacher_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
            'day_of_week' => ['required', 'string'],
            'period_id' => ['nullable', 'integer'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'room_id' => ['nullable', 'integer'],
            'term_id' => ['nullable', 'integer'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
        ]);

        return response()->json([
            'conflicts' => app(PreviewTimetableConflictsAction::class)->execute($data),
        ]);
    }

    public function copyFromClass(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('manage_timetables'), 403);

        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'source_class_id' => ['required', 'integer', 'exists:classes,id'],
            'target_class_id' => ['required', 'integer', 'exists:classes,id'],
        ]);

        $result = app(CopyTimetableEntriesAction::class)->fromClass(
            (int) $data['source_class_id'],
            (int) $data['target_class_id'],
            (int) $data['academic_year_id'],
        );

        return redirect()
            ->route('academics.timetable.index', [
                'academic_year_id' => $data['academic_year_id'],
                'class_id' => $data['target_class_id'],
                'view' => 'class',
            ])
            ->with('success', "Copied {$result['copied']} slot(s); skipped {$result['skipped']}.");
    }

    public function copyWeek(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('manage_timetables'), 403);

        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'week_start' => ['nullable', 'date'],
        ]);

        $result = app(CopyTimetableEntriesAction::class)->shiftWeek(
            (int) $data['class_id'],
            (int) $data['academic_year_id'],
            $data['week_start'] ?? null,
        );

        return redirect()
            ->route('academics.timetable.index', [
                'academic_year_id' => $data['academic_year_id'],
                'class_id' => $data['class_id'],
                'view' => 'class',
            ])
            ->with('success', "Copied week: {$result['copied']} slot(s); skipped {$result['skipped']}.");
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('manage_timetables'), 403);

        $yearId = $request->integer('academic_year_id');
        $rows = Timetable::query()
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
            ->orderBy('day_of_week')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'day', 'period_id', 'start', 'end', 'class_id', 'subject_id', 'teacher_id', 'room_id']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->day_of_week,
                    $row->period_id,
                    $row->start_time?->format('H:i'),
                    $row->end_time?->format('H:i'),
                    $row->class_id,
                    $row->subject_id,
                    $row->teacher_id,
                    $row->room_id,
                ]);
            }

            fclose($handle);
        }, 'timetable.csv', ['Content-Type' => 'text/csv']);
    }

    private function persist(Request $request, ?Timetable $entry = null): RedirectResponse
    {
        abort_unless($request->user()?->can('manage_timetables'), 403);

        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'period_id' => ['nullable', 'integer', 'exists:periods,id'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'day_of_week' => ['required', Rule::in(self::DAYS)],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'allow_conflict' => ['sometimes', 'boolean'],
            'conflict_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            app(SaveTimetableEntryAction::class)->execute(
                $data,
                $entry,
                (bool) $request->user()?->can('timetables.allow_conflict'),
                $request->user()?->id,
            );
        } catch (TimetableConflictException $exception) {
            throw ValidationException::withMessages([
                'conflicts' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('academics.timetable.index', [
                'academic_year_id' => $data['academic_year_id'],
                'class_id' => $data['class_id'],
                'view' => 'class',
            ])
            ->with('success', $entry ? 'Slot updated.' : 'Slot added.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEntry(Timetable $row): array
    {
        return [
            'id' => $row->id,
            'class_id' => $row->class_id,
            'subject_id' => $row->subject_id,
            'teacher_id' => $row->teacher_id,
            'academic_year_id' => $row->academic_year_id,
            'term_id' => $row->term_id,
            'period_id' => $row->period_id,
            'room_id' => $row->room_id,
            'day_of_week' => $row->day_of_week,
            'start_time' => $row->start_time?->format('H:i'),
            'end_time' => $row->end_time?->format('H:i'),
            'valid_from' => $row->valid_from?->toDateString(),
            'valid_until' => $row->valid_until?->toDateString(),
            'room' => $row->room,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function substitutions(?int $yearId): array
    {
        $absentTeacherIds = TeacherAbsence::query()
            ->where('status', 'approved')
            ->pluck('teacher_id');

        if ($absentTeacherIds->isEmpty()) {
            return [];
        }

        $entryIds = Timetable::query()
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
            ->whereIn('teacher_id', $absentTeacherIds)
            ->pluck('id');

        return SubstitutionRequest::query()
            ->with('assignment')
            ->whereIn('timetable_entry_id', $entryIds)
            ->where('status', 'assigned')
            ->get()
            ->map(fn (SubstitutionRequest $request) => [
                'timetable_id' => $request->timetable_entry_id,
                'absent_teacher_id' => $request->absent_teacher_id,
                'substitute_teacher_id' => $request->assignment?->substitute_teacher_id,
                'date' => $request->date?->toDateString(),
            ])
            ->all();
    }
}
