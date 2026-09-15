<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ListTeacherMeetingsAction;
use App\Domains\People\Actions\ResolveTeacherForUserAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Who is coming to see me, and when."
 *
 * Thin (rule 5): the gate is a teacher row, the scope is that teacher's id, and
 * both live in actions. There is no permission to hold — a teacher seeing the
 * bookings on their **own** slots is not a widening of anything, which is the
 * difference between this and the review-queue gap (KNOWN_ISSUES #28) that has
 * to wait on the owner.
 */
class TeachMeetingController extends Controller
{
    public function index(Request $request): Response
    {
        $teacher = $this->teacher($request);

        return Inertia::render('Academics/Teach/Meetings', [
            'teacher' => $teacher,
            'slots' => app(ListTeacherMeetingsAction::class)->execute($teacher['id']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $teacher = $this->teacher($request);
        $slots = app(ListTeacherMeetingsAction::class)->execute($teacher['id']);

        return response()->streamDownload(function () use ($slots): void {
            $handle = fopen('php://output', 'w');
            Csv::put($handle, ['date', 'start', 'end', 'class', 'room', 'booked', 'capacity', 'families', 'notes']);
            foreach ($slots as $slot) {
                Csv::put($handle, [
                    $slot['date'],
                    $slot['start_time'],
                    $slot['end_time'],
                    $slot['class_name'] ?? '',
                    $slot['room_name'] ?? '',
                    $slot['booked'],
                    $slot['capacity'],
                    collect($slot['bookings'])->pluck('student_name')->join(', '),
                    $slot['notes'] ?? '',
                ]);
            }
            fclose($handle);
        }, 'my-meetings.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{id: int, name: string}
     */
    private function teacher(Request $request): array
    {
        abort_unless($request->user() !== null, 403);
        $teacher = app(ResolveTeacherForUserAction::class)->execute((int) $request->user()->id);
        abort_if($teacher === null, 403);

        return $teacher;
    }
}
