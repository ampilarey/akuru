<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ListClassAttendanceAction;
use App\Domains\Academics\Actions\ListClassRosterAction;
use App\Domains\Academics\Actions\RecordDailyAttendanceAction;
use App\Domains\Academics\Actions\ResolveAttendanceSettingsAction;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailyAttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('mark_attendance') || $request->user()?->can('manage_attendance'), 403);

        $year = $this->year($request);
        $classId = $request->integer('class_id') ?: null;
        $date = $request->string('date')->toString() ?: now()->timezone(config('app.timezone'))->toDateString();
        $class = $classId ? ClassRoom::query()->find($classId) : null;
        $settings = app(ResolveAttendanceSettingsAction::class)->execute();

        return Inertia::render('Academics/Attendance/Daily', [
            'yearId' => $year?->id,
            'classId' => $class?->id,
            'date' => $date,
            'mode' => $settings['mode']->value,
            'years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name']),
            'classes' => ClassRoom::query()
                ->when($year, fn ($query) => $query->where('academic_year_id', $year->id))
                ->orderBy('name')
                ->get(['id', 'name', 'section', 'academic_year_id']),
            'statuses' => array_map(fn (AttendanceStatus $status) => $status->value, AttendanceStatus::cases()),
            'roster' => $class ? app(ListClassRosterAction::class)->execute($class->id) : collect(),
            'marks' => $class
                ? app(ListClassAttendanceAction::class)->execute([
                    'class_id' => $class->id,
                    'from' => $date,
                    'to' => $date,
                    'academic_year_id' => $year?->id,
                ])->where('period_id', null)->values()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('mark_attendance') || $request->user()?->can('manage_attendance'), 403);

        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'date' => ['required', 'date'],
            'attendance' => ['required', 'array', 'min:1'],
            'attendance.*.student_id' => ['required', 'integer'],
            'attendance.*.status' => ['required', 'string'],
            'attendance.*.minutes_late' => ['nullable', 'integer', 'min:0'],
        ]);

        $class = ClassRoom::query()->findOrFail((int) $data['class_id']);
        app(RecordDailyAttendanceAction::class)->execute(
            $class,
            $data['date'],
            $data['attendance'],
            (int) $request->user()->id,
        );

        return redirect()
            ->route('academics.attendance.daily', [
                'academic_year_id' => $class->academic_year_id,
                'class_id' => $class->id,
                'date' => $data['date'],
            ])
            ->with('success', 'Daily attendance saved.');
    }

    private function year(Request $request): ?AcademicYear
    {
        $yearId = $request->integer('academic_year_id');

        if ($yearId) {
            return AcademicYear::query()->find($yearId);
        }

        return AcademicYear::query()->where('status', 'active')->first();
    }
}
