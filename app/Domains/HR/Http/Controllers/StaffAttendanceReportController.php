<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\Academics\Actions\ListAcademicYearsAction;
use App\Domains\HR\Actions\ReportStaffAttendanceAction;
use App\Domains\People\Actions\ListStaffProfilesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffAttendanceReportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $filters = $this->filters($request);
        $report = app(ReportStaffAttendanceAction::class)->execute($filters);

        return Inertia::render('HR/Attendance/Reports', [
            'filters' => $filters,
            'years' => app(ListAcademicYearsAction::class)->execute()->values(),
            'departments' => app(ListStaffProfilesAction::class)->execute()
                ->pluck('department')
                ->filter()
                ->unique()
                ->values(),
            'late' => $report['late'],
            'absence' => $report['absence'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $kind = $request->string('kind')->toString() ?: 'late';
        $report = app(ReportStaffAttendanceAction::class)->execute($this->filters($request));
        $rows = $kind === 'absence' ? $report['absence'] : $report['late'];
        $headers = $kind === 'absence'
            ? ['staff_profile_id', 'staff_name', 'department', 'absent_count', 'half_day_count']
            : ['staff_profile_id', 'staff_name', 'department', 'late_count', 'minutes_late'];

        return response()->streamDownload(function () use ($rows, $headers): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn (string $key) => $row[$key] ?? '', $headers));
            }
            fclose($out);
        }, 'staff-attendance-'.$kind.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{academic_year_id: int|null, from: string|null, to: string|null, department: string|null}
     */
    private function filters(Request $request): array
    {
        return [
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
            'department' => $request->string('department')->toString() ?: null,
        ];
    }
}
