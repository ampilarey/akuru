<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\SaveCalendarDayAction;
use App\Domains\Academics\Enums\CalendarDayType;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\CalendarDay;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CalendarDayController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('calendar.manage'), 403);

        $year = $this->year($request);

        $days = CalendarDay::query()
            ->when($year, fn ($query) => $query->where('academic_year_id', $year->id))
            ->orderBy('date')
            ->get()
            ->map(fn (CalendarDay $day) => $this->serialize($day));

        return Inertia::render('Academics/Calendar/Index', [
            'yearId' => $year?->id,
            'yearStart' => $year?->start_date?->toDateString(),
            'yearEnd' => $year?->end_date?->toDateString(),
            'years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'status', 'start_date', 'end_date']),
            'types' => array_map(fn (CalendarDayType $type) => $type->value, CalendarDayType::cases()),
            'days' => $days,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('calendar.manage'), 403);

        app(SaveCalendarDayAction::class)->execute($this->validated($request));

        return redirect()
            ->route('academics.calendar.index', $request->only(['academic_year_id']))
            ->with('success', 'Calendar day saved.');
    }

    public function update(Request $request, CalendarDay $calendarDay): RedirectResponse
    {
        abort_unless($request->user()?->can('calendar.manage'), 403);

        app(SaveCalendarDayAction::class)->execute($this->validated($request), $calendarDay);

        return redirect()
            ->route('academics.calendar.index', ['academic_year_id' => $calendarDay->academic_year_id])
            ->with('success', 'Calendar day updated.');
    }

    public function destroy(Request $request, CalendarDay $calendarDay): RedirectResponse
    {
        abort_unless($request->user()?->can('calendar.manage'), 403);

        $yearId = $calendarDay->academic_year_id;
        $calendarDay->delete();

        return redirect()
            ->route('academics.calendar.index', ['academic_year_id' => $yearId])
            ->with('success', 'Calendar day removed.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('calendar.manage'), 403);

        $yearId = $request->integer('academic_year_id');
        $rows = CalendarDay::query()
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
            ->orderBy('date')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'date', 'type', 'title', 'title_arabic', 'title_dhivehi', 'affects_timetable', 'event_id', 'notes']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->date?->toDateString(),
                    $row->type->value,
                    $row->title,
                    $row->title_arabic,
                    $row->title_dhivehi,
                    $row->affects_timetable ? '1' : '0',
                    $row->event_id,
                    $row->notes,
                ]);
            }

            fclose($handle);
        }, 'calendar-days.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'date' => ['required', 'date'],
            'type' => ['required', Rule::enum(CalendarDayType::class)],
            'title' => ['required', 'string', 'max:255'],
            'title_arabic' => ['nullable', 'string', 'max:255'],
            'title_dhivehi' => ['nullable', 'string', 'max:255'],
            'affects_timetable' => ['sometimes', 'boolean'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function year(Request $request): ?AcademicYear
    {
        $yearId = $request->integer('academic_year_id');

        if ($yearId) {
            return AcademicYear::query()->find($yearId);
        }

        return AcademicYear::query()->where('status', 'active')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CalendarDay $day): array
    {
        return [
            'id' => $day->id,
            'academic_year_id' => $day->academic_year_id,
            'date' => $day->date?->toDateString(),
            'type' => $day->type->value,
            'title' => $day->title,
            'title_arabic' => $day->title_arabic,
            'title_dhivehi' => $day->title_dhivehi,
            'affects_timetable' => $day->affects_timetable,
            'event_id' => $day->event_id,
            'notes' => $day->notes,
        ];
    }
}
