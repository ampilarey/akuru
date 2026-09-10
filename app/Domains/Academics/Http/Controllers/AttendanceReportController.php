<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ListClassAttendanceAction;
use App\Domains\Academics\Actions\ListTardySummaryAction;
use App\Domains\Academics\Actions\ResolveAttendanceSettingsAction;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('view_attendance') || $request->user()?->can('manage_attendance'), 403);

        $filters = $this->filters($request);
        $lister = app(ListClassAttendanceAction::class);

        return Inertia::render('Academics/Attendance/Index', [
            'filters' => $filters,
            'years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name']),
            'classes' => ClassRoom::query()->orderBy('name')->get(['id', 'name', 'section', 'academic_year_id']),
            'statuses' => array_map(fn (AttendanceStatus $status) => $status->value, AttendanceStatus::cases()),
            'rows' => $lister->execute($filters),
            'chronic' => $lister->chronic($filters['academic_year_id'] ?? null),
            'unexcused' => $lister->unexcused($filters['academic_year_id'] ?? null),
            // E10: lateness has been recorded since 2026-08 and never
            // aggregated. Reported beside the absence figures, never folded in.
            'tardies' => app(ListTardySummaryAction::class)->execute($filters),
            'tardiesPerAbsence' => app(ResolveAttendanceSettingsAction::class)->execute()['tardies_per_absence'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('view_attendance') || $request->user()?->can('manage_attendance'), 403);

        $kind = $request->string('kind')->toString() ?: 'sheet';
        $lister = app(ListClassAttendanceAction::class);
        $filters = $this->filters($request);

        if ($kind === 'chronic') {
            $rows = $lister->chronic($filters['academic_year_id'] ?? null);
            $headers = ['student_id', 'student_number', 'student_name', 'absent_days'];
        } elseif ($kind === 'tardies') {
            $rows = app(ListTardySummaryAction::class)->execute($filters);
            $headers = ['student_id', 'student_number', 'student_name', 'tardies', 'minutes_late',
                'early_departures', 'absent_days', 'absences_from_tardies', 'effective_absences'];
        } elseif ($kind === 'unexcused') {
            $rows = $lister->unexcused($filters['academic_year_id'] ?? null);
            $headers = ['id', 'date', 'student_name', 'student_number', 'class_name', 'status'];
        } else {
            $rows = $lister->execute($filters);
            $headers = ['id', 'date', 'student_name', 'student_number', 'class_name', 'status', 'source', 'minutes_late'];
        }

        return response()->streamDownload(function () use ($rows, $headers): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($key) => $row[$key] ?? '', $headers));
            }
            fclose($handle);
        }, 'attendance-'.$kind.'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{academic_year_id: int|null, class_id: int|null, student_id: int|null, from: string|null, to: string|null, status: string|null}
     */
    private function filters(Request $request): array
    {
        return [
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
            'class_id' => $request->integer('class_id') ?: null,
            'student_id' => $request->integer('student_id') ?: null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];
    }
}
