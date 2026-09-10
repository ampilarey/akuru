<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ListAbsencesForDayAction;
use App\Domains\Academics\Actions\ListClassesForYearAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The office's morning list: who is not in, and who nobody has heard from.
 */
class AbsencesTodayController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeUse($request);

        $filters = $this->filters($request);

        return Inertia::render('Academics/Attendance/AbsencesToday', [
            ...app(ListAbsencesForDayAction::class)->execute($filters),
            'classId' => $filters['class_id'],
            'onlyUnexplained' => $filters['only_unexplained'],
            'classes' => app(ListClassesForYearAction::class)->execute()->values()->all(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeUse($request);

        $payload = app(ListAbsencesForDayAction::class)->execute($this->filters($request));

        return response()->streamDownload(function () use ($payload): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['date', 'student_number', 'student', 'class', 'periods_missed', 'periods', 'note_status', 'reason', 'unexplained']);

            foreach ($payload['students'] as $row) {
                fputcsv($handle, [
                    $payload['date'],
                    $row['student_number'],
                    $row['student_name'],
                    $row['class_name'],
                    $row['periods_missed'],
                    implode(' ', $row['periods']),
                    $row['note_status'] ?? '',
                    $row['note_reason'] ?? '',
                    $row['is_unexplained'] ? '1' : '0',
                ]);
            }

            fclose($handle);
        }, 'absences-'.$payload['date'].'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{date: ?string, class_id: ?int, only_unexplained: bool}
     */
    private function filters(Request $request): array
    {
        return [
            'date' => $request->string('date')->toString() ?: null,
            'class_id' => $request->integer('class_id') ?: null,
            'only_unexplained' => $request->boolean('only_unexplained'),
        ];
    }

    private function authorizeUse(Request $request): void
    {
        // The same pair that guards the attendance screens this reads from:
        // a list of who is absent is the attendance data, rearranged.
        abort_unless(
            $request->user()?->can('mark_attendance') || $request->user()?->can('manage_attendance'),
            403,
        );
    }
}
