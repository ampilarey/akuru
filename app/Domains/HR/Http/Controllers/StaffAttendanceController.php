<?php

namespace App\Domains\HR\Http\Controllers;

use App\Domains\Academics\Actions\ListAcademicYearsAction;
use App\Domains\Academics\Actions\ResolveAcademicYearForDateAction;
use App\Domains\HR\Actions\AutoFillHolidayStaffAttendanceAction;
use App\Domains\HR\Actions\ImportStaffAttendanceCsvAction;
use App\Domains\HR\Actions\ListStaffAttendanceAction;
use App\Domains\HR\Contracts\StaffAttendanceWriterInterface;
use App\Domains\HR\DTOs\StaffAttendanceDTO;
use App\Domains\HR\Enums\StaffAttendanceSource;
use App\Domains\HR\Enums\StaffAttendanceStatus;
use App\Domains\People\Actions\ListStaffProfilesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffAttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $date = $request->string('date')->toString() ?: now('Indian/Maldives')->toDateString();
        $year = app(ResolveAcademicYearForDateAction::class)->execute($date);

        return Inertia::render('HR/Attendance/Index', [
            'date' => $date,
            'years' => app(ListAcademicYearsAction::class)->execute()->values(),
            'academicYearId' => $year['id'] ?? null,
            'staff' => app(ListStaffProfilesAction::class)->execute(['status' => 'active'])->values(),
            'rows' => app(ListStaffAttendanceAction::class)->execute([
                'date' => $date,
                'academic_year_id' => $year['id'] ?? null,
            ])->values(),
            'statuses' => array_map(fn (StaffAttendanceStatus $status) => $status->value, StaffAttendanceStatus::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $data = $request->validate([
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::enum(StaffAttendanceStatus::class)],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'minutes_late' => ['nullable', 'integer', 'min:0', 'max:720'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $year = app(ResolveAcademicYearForDateAction::class)->execute($data['date']);
        abort_unless($year !== null, 422, 'No academic year covers this date.');

        app(StaffAttendanceWriterInterface::class)->record(new StaffAttendanceDTO(
            staffProfileId: (int) $data['staff_profile_id'],
            academicYearId: (int) $year['id'],
            date: $data['date'],
            status: StaffAttendanceStatus::from($data['status']),
            source: StaffAttendanceSource::Manual,
            markedBy: $request->user()?->id,
            checkIn: isset($data['check_in']) ? $data['check_in'].':00' : null,
            checkOut: isset($data['check_out']) ? $data['check_out'].':00' : null,
            minutesLate: isset($data['minutes_late']) ? (int) $data['minutes_late'] : null,
            remarks: $data['remarks'] ?? null,
        ));

        return redirect()
            ->route('hr.attendance.index', ['date' => $data['date']])
            ->with('success', 'Staff attendance saved.');
    }

    public function fillHolidays(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'date' => ['nullable', 'date'],
        ]);

        app(AutoFillHolidayStaffAttendanceAction::class)->execute(
            (int) $data['academic_year_id'],
            $request->user()?->id,
        );

        return redirect()
            ->route('hr.attendance.index', array_filter(['date' => $data['date'] ?? null]))
            ->with('success', 'Holiday attendance filled.');
    }

    public function import(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $data = $request->validate([
            'file' => ['required', 'file'],
        ]);

        $csv = (string) file_get_contents($data['file']->getRealPath());
        $result = app(ImportStaffAttendanceCsvAction::class)->execute($csv, $request->user()?->id);

        return redirect()
            ->route('hr.attendance.index')
            ->with('success', $result['imported'].' attendance rows imported.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('hr.manage'), 403);

        $date = $request->string('date')->toString() ?: now('Indian/Maldives')->toDateString();
        $year = app(ResolveAcademicYearForDateAction::class)->execute($date);
        $rows = app(ListStaffAttendanceAction::class)->execute([
            'date' => $date,
            'academic_year_id' => $year['id'] ?? null,
        ]);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['staff_profile_id', 'staff_number', 'staff_name', 'department', 'date', 'status', 'source', 'minutes_late', 'remarks']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['staff_profile_id'],
                    $row['staff_number'],
                    $row['staff_name'],
                    $row['department'],
                    $row['date'],
                    $row['status'],
                    $row['source'],
                    $row['minutes_late'],
                    $row['remarks'],
                ]);
            }
            fclose($out);
        }, 'staff-attendance.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
