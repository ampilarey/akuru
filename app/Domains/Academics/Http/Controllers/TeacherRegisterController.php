<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\ListClassAttendanceAction;
use App\Domains\Academics\Actions\ListClassRosterAction;
use App\Domains\Academics\Actions\ListPlanTopicsForRegisterAction;
use App\Domains\Academics\Actions\ListTeacherTodayRegistersAction;
use App\Domains\Academics\Actions\RecordRegisterAttendanceAction;
use App\Domains\Academics\Actions\ResolveAttendanceSettingsAction;
use App\Domains\Academics\Actions\ResolveTeacherIdForUserAction;
use App\Domains\Academics\Actions\SubmitRegisterAction;
use App\Domains\Academics\Enums\AttendanceMode;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\LessonLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherRegisterController extends Controller
{
    public function today(Request $request): Response
    {
        abort_unless($request->user()?->can('registers.fill') || $request->user()?->can('registers.manage'), 403);

        $teacherId = app(ResolveTeacherIdForUserAction::class)->execute($request->user()?->id);
        $date = $request->string('date')->toString() ?: now()->timezone(config('app.timezone'))->toDateString();

        $registers = $teacherId
            ? app(ListTeacherTodayRegistersAction::class)->execute($teacherId, $date)
            : collect();

        return Inertia::render('Academics/Registers/Today', [
            'date' => $date,
            'teacherId' => $teacherId,
            'registers' => $registers,
        ]);
    }

    public function show(Request $request, LessonLog $lessonLog): Response
    {
        $this->authorizeView($request, $lessonLog);

        $settings = app(ResolveAttendanceSettingsAction::class)->execute();
        $perLesson = $settings['mode'] === AttendanceMode::PerLesson;

        return Inertia::render('Academics/Registers/Show', [
            'register' => app(ListTeacherTodayRegistersAction::class)->serialize(collect([$lessonLog]))->first(),
            'topics' => app(ListPlanTopicsForRegisterAction::class)->execute($lessonLog),
            'homework' => $lessonLog->homework,
            'materials' => is_array($lessonLog->materials) ? implode(', ', $lessonLog->materials) : '',
            'notes' => $lessonLog->notes,
            'canSubmit' => $this->canSubmit($request, $lessonLog),
            'attendanceMode' => $settings['mode']->value,
            'attendanceStatuses' => array_map(fn (AttendanceStatus $status) => $status->value, AttendanceStatus::cases()),
            'roster' => $perLesson ? app(ListClassRosterAction::class)->execute((int) $lessonLog->classroom_id) : collect(),
            'marks' => $perLesson
                ? app(ListClassAttendanceAction::class)->execute([
                    'class_id' => $lessonLog->classroom_id,
                    'from' => $lessonLog->date?->toDateString(),
                    'to' => $lessonLog->date?->toDateString(),
                ])->where('period_id', $lessonLog->period_id)->values()
                : collect(),
        ]);
    }

    public function update(Request $request, LessonLog $lessonLog): RedirectResponse
    {
        abort_unless($request->user()?->can('registers.fill') || $request->user()?->can('registers.manage'), 403);

        $data = $request->validate([
            'plan_topic_id' => ['nullable', 'integer', 'exists:plan_topics,id'],
            'taught_summary' => ['nullable', 'string', 'max:5000'],
            'homework' => ['nullable', 'string', 'max:5000'],
            'materials' => ['nullable'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attendance' => ['nullable', 'array'],
            'attendance.*.student_id' => ['required_with:attendance', 'integer'],
            'attendance.*.status' => ['required_with:attendance', 'string'],
            'attendance.*.minutes_late' => ['nullable', 'integer', 'min:0'],
        ]);

        $log = app(SubmitRegisterAction::class)->execute(
            $lessonLog,
            $data,
            (int) $request->user()->id,
            (bool) $request->user()?->can('registers.manage'),
        );

        if (! empty($data['attendance'])) {
            abort_unless($request->user()?->can('mark_attendance') || $request->user()?->can('registers.manage'), 403);
            app(RecordRegisterAttendanceAction::class)->execute(
                $log,
                $data['attendance'],
                (int) $request->user()->id,
            );
        }

        return redirect()
            ->route('academics.registers.show', $lessonLog)
            ->with('success', 'Register submitted.');
    }

    private function authorizeView(Request $request, LessonLog $lessonLog): void
    {
        $user = $request->user();
        abort_unless($user?->can('registers.fill') || $user?->can('registers.manage'), 403);

        if ($user?->can('registers.manage')) {
            return;
        }

        $teacherId = app(ResolveTeacherIdForUserAction::class)->execute($user?->id);
        abort_unless($teacherId !== null && (int) $lessonLog->teacher_id === $teacherId, 403);
    }

    private function canSubmit(Request $request, LessonLog $lessonLog): bool
    {
        if ($lessonLog->status?->value === 'locked') {
            return false;
        }

        $user = $request->user();
        if ($user?->can('registers.manage')) {
            return true;
        }

        $teacherId = app(ResolveTeacherIdForUserAction::class)->execute($user?->id);

        return $teacherId !== null && (int) $lessonLog->teacher_id === $teacherId;
    }
}
