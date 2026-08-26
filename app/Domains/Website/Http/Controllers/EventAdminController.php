<?php

namespace App\Domains\Website\Http\Controllers;

use App\Domains\Academics\Actions\ListAcademicYearsAction;
use App\Domains\People\Actions\ListStudentsAction;
use App\Domains\Website\Actions\ConfirmEventRegistrationAction;
use App\Domains\Website\Actions\ListEventRegistrationsAction;
use App\Domains\Website\Actions\ListSchoolEventsAction;
use App\Domains\Website\Actions\OpenEventSecondRoundAction;
use App\Domains\Website\Actions\RegisterForEventAction;
use App\Domains\Website\Actions\SaveEventAction;
use App\Domains\Website\Models\Event;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventAdminController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('events.manage'), 403);

        return Inertia::render('Website/Events/Index', [
            'events' => app(ListSchoolEventsAction::class)->execute($request->only(['academic_year_id', 'status']))->values(),
            'years' => app(ListAcademicYearsAction::class)->execute()->values(),
            'types' => ['conference', 'workshop', 'seminar', 'competition', 'celebration', 'meeting', 'other'],
            'statuses' => ['draft', 'published', 'cancelled', 'completed'],
            'registrationTypes' => ['none', 'required', 'optional'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('events.manage'), 403);
        app(SaveEventAction::class)->execute($this->eventPayload($request));

        return redirect()->route('academics.events.index')->with('success', 'Event saved.');
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()?->can('events.manage'), 403);
        app(SaveEventAction::class)->execute($this->eventPayload($request), $event);

        return redirect()->route('academics.events.show', $event)->with('success', 'Event updated.');
    }

    public function show(Request $request, Event $event): Response
    {
        abort_unless($request->user()?->can('events.manage'), 403);

        $listed = app(ListSchoolEventsAction::class)->execute()->firstWhere('id', $event->id);

        return Inertia::render('Website/Events/Show', [
            'event' => $listed,
            'registrations' => app(ListEventRegistrationsAction::class)->execute($event->id)->values(),
            'students' => app(ListStudentsAction::class)->execute()->map(fn ($student) => [
                'id' => $student->id,
                'name' => trim(($student->first_name ?? '').' '.($student->last_name ?? '')),
            ])->values(),
            'types' => ['conference', 'workshop', 'seminar', 'competition', 'celebration', 'meeting', 'other'],
            'statuses' => ['draft', 'published', 'cancelled', 'completed'],
            'registrationTypes' => ['none', 'required', 'optional'],
            'years' => app(ListAcademicYearsAction::class)->execute()->values(),
        ]);
    }

    public function register(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()?->can('events.manage'), 403);
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        app(RegisterForEventAction::class)->execute([
            ...$data,
            'event_id' => $event->id,
            'registration_source' => 'admin',
            'fallback_email' => $request->user()->email,
            'parent_user_id' => null,
        ]);

        return redirect()->route('academics.events.show', $event)->with('success', 'Registration saved.');
    }

    public function confirm(Request $request, Event $event, int $registration): RedirectResponse
    {
        abort_unless($request->user()?->can('events.manage'), 403);
        app(ConfirmEventRegistrationAction::class)->execute($registration);

        return redirect()->route('academics.events.show', $event)->with('success', 'Registration confirmed.');
    }

    public function secondRound(Request $request, Event $event): RedirectResponse
    {
        abort_unless($request->user()?->can('events.manage'), 403);
        $result = app(OpenEventSecondRoundAction::class)->execute($event->id);

        return redirect()
            ->route('academics.events.show', $event)
            ->with('success', 'Second round opened. Promoted '.$result['promoted'].' waitlisted registration(s).');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('events.manage'), 403);
        $rows = app(ListSchoolEventsAction::class)->execute($request->only(['academic_year_id', 'status']));

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id', 'title', 'title_dv', 'title_ar', 'location', 'start_date', 'end_date',
                'type', 'status', 'registration_type', 'min_attendees', 'max_attendees',
                'occupying', 'waitlisted', 'waitlist_enabled', 'requires_parent_confirmation',
                'is_elective', 'second_round_opens_at',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'], $row['title'], $row['title_dv'], $row['title_ar'], $row['location'],
                    $row['start_date'], $row['end_date'], $row['type'], $row['status'],
                    $row['registration_type'], $row['min_attendees'], $row['max_attendees'],
                    $row['occupying'], $row['waitlisted'], $row['waitlist_enabled'] ? '1' : '0',
                    $row['requires_parent_confirmation'] ? '1' : '0', $row['is_elective'] ? '1' : '0',
                    $row['second_round_opens_at'],
                ]);
            }
            fclose($out);
        }, 'events.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportRegistrations(Request $request, Event $event): StreamedResponse
    {
        abort_unless($request->user()?->can('events.manage'), 403);
        $rows = app(ListEventRegistrationsAction::class)->execute($event->id);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'student_id', 'student_name', 'email', 'status', 'waitlist_position', 'source', 'created_at']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'], $row['student_id'], $row['student_name'], $row['email'],
                    $row['status'], $row['waitlist_position'], $row['registration_source'], $row['created_at'],
                ]);
            }
            fclose($out);
        }, 'event-'.$event->id.'-registrations.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'title_dv' => ['nullable', 'string', 'max:255'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string'],
            'location' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'type' => ['required', 'string', 'in:conference,workshop,seminar,competition,celebration,meeting,other'],
            'status' => ['required', 'string', 'in:draft,published,cancelled,completed'],
            'registration_type' => ['required', 'string', 'in:none,required,optional'],
            'max_attendees' => ['nullable', 'integer', 'min:0'],
            'min_attendees' => ['nullable', 'integer', 'min:0'],
            'waitlist_enabled' => ['sometimes', 'boolean'],
            'requires_parent_confirmation' => ['sometimes', 'boolean'],
            'is_elective' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'registration_start' => ['nullable', 'date'],
            'registration_deadline' => ['nullable', 'date'],
        ]);
    }
}
