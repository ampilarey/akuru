<?php

namespace App\Domains\Offerings\Http\Controllers;

use App\Domains\Offerings\Actions\BulkMarkOfferingAttendanceAction;
use App\Domains\Offerings\Actions\ListOfferingSessionsAction;
use App\Domains\Offerings\Actions\ListSessionAttendanceAction;
use App\Domains\Offerings\Actions\RecordOfferingAttendanceAction;
use App\Domains\Offerings\Actions\SaveOfferingHalaqaLinkAction;
use App\Domains\Offerings\Actions\SaveOfferingHalaqaSessionLinkAction;
use App\Domains\Offerings\Actions\SaveOfferingSessionAction;
use App\Domains\Offerings\Models\CourseOfferingSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfferingSessionController extends Controller
{
    public function index(Request $request, int $offering): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render('Offerings/Catalog/Sessions', app(ListOfferingSessionsAction::class)->execute($offering));
    }

    public function store(Request $request, int $offering): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'session_type' => ['required', 'string', 'max:32'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'online_meeting_url' => ['nullable', 'string', 'max:500'],
            'teacher_user_id' => ['nullable', 'integer'],
            'is_required' => ['nullable', 'boolean'],
        ]);
        app(SaveOfferingSessionAction::class)->execute($data + [
            'course_offering_id' => $offering,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('catalog.offerings.sessions.index', $offering)->with('success', 'Session saved.');
    }

    public function update(Request $request, int $offering, int $session): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $model = CourseOfferingSession::query()->where('course_offering_id', $offering)->findOrFail($session);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'session_type' => ['required', 'string', 'max:32'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'online_meeting_url' => ['nullable', 'string', 'max:500'],
            'teacher_user_id' => ['nullable', 'integer'],
            'is_required' => ['nullable', 'boolean'],
        ]);
        app(SaveOfferingSessionAction::class)->execute($data + [
            'course_offering_id' => $offering,
        ], $model);

        return redirect()->route('catalog.offerings.sessions.index', $offering)->with('success', 'Session updated.');
    }

    public function storeHalaqa(Request $request, int $offering): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $data = $request->validate([
            'hifz_program_id' => ['required', 'integer'],
        ]);
        app(SaveOfferingHalaqaLinkAction::class)->execute($data + [
            'course_offering_id' => $offering,
        ]);

        return redirect()->route('catalog.offerings.sessions.index', $offering)
            ->with('success', 'Halaqa program linked.');
    }

    public function storeHalaqaSession(Request $request, int $offering, int $session): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        CourseOfferingSession::query()->where('course_offering_id', $offering)->findOrFail($session);
        $data = $request->validate([
            'hifz_session_id' => ['required', 'integer'],
        ]);
        app(SaveOfferingHalaqaSessionLinkAction::class)->execute($data + [
            'course_offering_session_id' => $session,
        ]);

        return redirect()->route('catalog.offerings.sessions.index', $offering)
            ->with('success', 'Halaqa session linked.');
    }

    public function attendance(Request $request, int $offering, int $session): Response
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        return Inertia::render('Offerings/Catalog/Attendance', app(ListSessionAttendanceAction::class)->execute($session));
    }

    public function mark(Request $request, int $offering, int $session): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $data = $request->validate([
            'enrollment_id' => ['required', 'integer'],
            'status' => ['required', 'string', 'max:20'],
            'attendance_mode' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);
        app(RecordOfferingAttendanceAction::class)->execute($data + [
            'course_offering_session_id' => $session,
            'marked_by' => $request->user()?->id,
        ]);

        return redirect()->route('catalog.offerings.sessions.attendance', [$offering, $session])->with('success', 'Attendance saved.');
    }

    public function bulk(Request $request, int $offering, int $session): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        CourseOfferingSession::query()->where('course_offering_id', $offering)->findOrFail($session);
        $data = $request->validate([
            'status' => ['required', 'string', 'max:20'],
            'attendance_mode' => ['nullable', 'string', 'max:20'],
        ]);
        app(BulkMarkOfferingAttendanceAction::class)->execute(
            $session,
            $data['status'],
            $data['attendance_mode'] ?? null,
            (int) $request->user()->id,
        );

        return redirect()->route('catalog.offerings.sessions.attendance', [$offering, $session])->with('success', 'Roster marked.');
    }

    public function export(Request $request, int $offering): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        $payload = app(ListOfferingSessionsAction::class)->execute($offering);

        return response()->streamDownload(function () use ($payload): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'title', 'session_type', 'starts_at', 'location_name', 'is_required']);
            foreach ($payload['sessions'] as $row) {
                fputcsv($out, [$row['id'], $row['title'], $row['session_type'], $row['starts_at'], $row['location_name'], $row['is_required'] ? 'yes' : 'no']);
            }
            fclose($out);
        }, 'offering-sessions.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
