<?php

namespace App\Domains\Portal\Http\Controllers;

use App\Domains\Academics\Actions\BookMeetingSlotAction;
use App\Domains\Academics\Actions\CancelMeetingBookingAction;
use App\Domains\Academics\Actions\ListPortalMeetingSlotsAction;
use App\Domains\People\Actions\ListGuardianChildrenAction;
use App\Domains\People\Actions\ResolveStudentForUserAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalMeetingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null, 403);
        $people = $this->people((int) $request->user()->id);
        $board = app(ListPortalMeetingSlotsAction::class)->execute(array_column($people, 'id'));

        return Inertia::render('Portal/Meetings', [
            'children' => $people,
            'slots' => $board['slots'],
            'bookings' => $board['bookings'],
            'csvUrl' => '/portal/meetings/export',
        ]);
    }

    public function book(Request $request, int $slot): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
        ]);
        $ids = array_column($this->people((int) $request->user()->id), 'id');
        abort_unless(in_array((int) $data['student_id'], $ids, true), 403);

        app(BookMeetingSlotAction::class)->execute(
            $slot,
            (int) $data['student_id'],
            (int) $request->user()->id,
        );

        return redirect()->route('portal.meetings')->with('success', 'Meeting booked.');
    }

    public function cancel(Request $request, int $booking): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);

        app(CancelMeetingBookingAction::class)->execute(
            $booking,
            (int) $request->user()->id,
        );

        return redirect()->route('portal.meetings')->with('success', 'Meeting cancelled.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user() !== null, 403);
        $people = $this->people((int) $request->user()->id);
        $board = app(ListPortalMeetingSlotsAction::class)->execute(array_column($people, 'id'));

        return response()->streamDownload(function () use ($board): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['student', 'date', 'start', 'end', 'teacher', 'title', 'status']);
            foreach ($board['bookings'] as $row) {
                fputcsv($out, [
                    $row['student_name'],
                    $row['date'],
                    $row['start_time'],
                    $row['end_time'],
                    $row['teacher_name'],
                    $row['title'],
                    $row['status'],
                ]);
            }
            fclose($out);
        }, 'portal-meetings.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<array{id: int, name: string, relationship: string}>
     */
    private function people(int $userId): array
    {
        $people = [];
        $self = app(ResolveStudentForUserAction::class)->execute($userId);
        if ($self !== null) {
            $people[] = [
                'id' => $self['id'],
                'name' => trim($self['first_name'].' '.$self['last_name']),
                'relationship' => 'self',
            ];
        }
        foreach (app(ListGuardianChildrenAction::class)->executeForGuardianUserId($userId) as $child) {
            $people[] = [
                'id' => (int) $child->id,
                'name' => trim(($child->first_name ?? '').' '.($child->last_name ?? '')),
                'relationship' => (string) ($child->relationship ?? 'child'),
            ];
        }

        $seen = [];
        $unique = [];
        foreach ($people as $person) {
            if (isset($seen[$person['id']])) {
                continue;
            }
            $seen[$person['id']] = true;
            $unique[] = $person;
        }

        return $unique;
    }
}
