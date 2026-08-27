<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\GenerateMeetingSlotsAction;
use App\Domains\Academics\Actions\ListMeetingSlotOptionsAction;
use App\Domains\Academics\Actions\ListMeetingSlotsAction;
use App\Domains\Academics\Actions\SaveMeetingSlotAction;
use App\Domains\Academics\Enums\MeetingSlotStatus;
use App\Domains\Academics\Models\MeetingSlot;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeetingSlotController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('meetings.manage'), 403);

        $yearId = $request->integer('academic_year_id') ?: null;
        $options = app(ListMeetingSlotOptionsAction::class)->execute($yearId ?: null);
        $yearId = $yearId ?: $options['yearId'];

        return Inertia::render('Academics/Meetings/Index', [
            ...$options,
            'yearId' => $yearId,
            'slots' => app(ListMeetingSlotsAction::class)->execute($yearId)->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('meetings.manage'), 403);

        $payload = $this->validated($request);
        $actorId = $request->user()?->id;

        if ($request->filled('slot_minutes')) {
            app(GenerateMeetingSlotsAction::class)->execute($payload + [
                'slot_minutes' => $request->integer('slot_minutes'),
            ], $actorId);
        } else {
            app(SaveMeetingSlotAction::class)->execute($payload, null, $actorId);
        }

        return redirect()
            ->route('academics.meetings.index', $request->only(['academic_year_id']))
            ->with('success', 'Meeting slots saved.');
    }

    public function update(Request $request, MeetingSlot $meetingSlot): RedirectResponse
    {
        abort_unless($request->user()?->can('meetings.manage'), 403);

        app(SaveMeetingSlotAction::class)->execute($this->validated($request), $meetingSlot, $request->user()?->id);

        return redirect()
            ->route('academics.meetings.index', ['academic_year_id' => $meetingSlot->academic_year_id])
            ->with('success', 'Meeting slot updated.');
    }

    public function destroy(Request $request, MeetingSlot $meetingSlot): RedirectResponse
    {
        abort_unless($request->user()?->can('meetings.manage'), 403);

        $yearId = $meetingSlot->academic_year_id;
        $hasBookings = $meetingSlot->bookings()->where('status', 'booked')->exists();
        if ($hasBookings) {
            $meetingSlot->status = MeetingSlotStatus::Cancelled;
            $meetingSlot->save();
        } else {
            $meetingSlot->delete();
        }

        return redirect()
            ->route('academics.meetings.index', ['academic_year_id' => $yearId])
            ->with('success', $hasBookings ? 'Meeting slot cancelled.' : 'Meeting slot removed.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('meetings.manage'), 403);

        $yearId = $request->integer('academic_year_id') ?: null;
        $rows = app(ListMeetingSlotsAction::class)->execute($yearId ?: null);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id', 'date', 'start', 'end', 'title', 'teacher', 'class', 'status',
                'capacity', 'booked', 'students',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['date'],
                    $row['start_time'],
                    $row['end_time'],
                    $row['title'],
                    $row['teacher_name'],
                    $row['class_name'],
                    $row['status'],
                    $row['capacity'],
                    $row['booked'],
                    collect($row['bookings'])->pluck('student_name')->implode('; '),
                ]);
            }
            fclose($out);
        }, 'meeting-slots.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'term_id' => ['nullable', 'integer'],
            'teacher_id' => ['required', 'integer'],
            'class_id' => ['nullable', 'integer'],
            'room_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'title_arabic' => ['nullable', 'string', 'max:255'],
            'title_dhivehi' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
