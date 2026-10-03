<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Actions\EndHifzEnrollmentAction;
use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Domains\People\Models\Student;
use App\Domains\People\Models\Teacher;
use App\Enums\Hifz\HifzEnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A programme's enrolments. Inertia since the Hifz port's first slice
 * (BACKLOG C1, STATUS §5jv); the gates and the defaults (the programme's
 * supervisor and teacher when the form names none) are as they were.
 * Since C16 slice N4 (STATUS §5nz) an enrolment can be ended — withdrawn,
 * transferred or completed — from the list, by whoever may update the
 * programme; until then the controller could create and not change.
 */
class HifzEnrollmentController extends Controller
{
    public function __construct(protected HifzScopeService $scope) {}

    public function index(HifzProgram $program): Response
    {
        $this->authorize('view', $program);

        $enrollments = $program->enrollments()->with(['student.user', 'teacher.user'])->paginate(20)
            ->through(fn (HifzEnrollment $enrollment): array => [
                'id' => $enrollment->id,
                'student' => $enrollment->student?->full_name,
                'teacher' => $enrollment->teacher?->full_name,
                'status' => $enrollment->status?->value,
                'ended' => (bool) $enrollment->status?->isEnded(),
                'ended_at' => $enrollment->ended_at?->toDateString(),
                'end_reason' => $enrollment->end_reason,
            ]);

        return Inertia::render('Hifz/Enrollments', [
            'program' => ['id' => $program->id, 'name' => $program->name],
            'enrollments' => $enrollments,
            'can_update' => auth()->user()->can('update', $program),
            'endings' => array_map(fn (HifzEnrollmentStatus $status) => $status->value, HifzEnrollmentStatus::endings()),
            'today' => now()->toDateString(),
            't' => Phrases::once('admin'),
        ]);
    }

    /** C16 slice N4: end an enrolment — withdrawn, transferred or completed — with a date and a note. */
    public function end(Request $request, HifzProgram $program, HifzEnrollment $enrollment): RedirectResponse
    {
        $this->authorize('update', $program);
        abort_unless((int) $enrollment->hifz_program_id === (int) $program->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_map(fn (HifzEnrollmentStatus $status) => $status->value, HifzEnrollmentStatus::endings()))],
            'ended_at' => 'required|date',
            'reason' => 'nullable|string|max:500',
        ]);

        app(EndHifzEnrollmentAction::class)->execute(
            $enrollment,
            HifzEnrollmentStatus::from($data['status']),
            $data['ended_at'],
            $data['reason'] ?? null,
            $request->user()?->id,
        );

        return back()->with('success', trans('admin.hifz_flash_ended', ['status' => trans('admin.hifz_enrollment_status_'.$data['status'])]));
    }

    public function create(HifzProgram $program): Response
    {
        $this->authorize('update', $program);

        return Inertia::render('Hifz/EnrollmentForm', [
            'program' => ['id' => $program->id, 'name' => $program->name],
            'students' => Student::with('user')->orderBy('first_name')->get()
                ->map(fn (Student $student): array => ['id' => (int) $student->id, 'name' => (string) $student->full_name])->values()->all(),
            'teachers' => Teacher::with('user')->get()
                ->map(fn (Teacher $teacher): array => ['id' => (int) $teacher->id, 'name' => (string) $teacher->full_name])->values()->all(),
            'today' => now()->toDateString(),
            't' => Phrases::once('admin'),
        ]);
    }

    public function store(Request $request, HifzProgram $program): RedirectResponse
    {
        $this->authorize('update', $program);

        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'supervisor_id' => 'nullable|exists:users,id',
            'start_date' => 'required|date',
            'target_completion_date' => 'nullable|date',
            'current_surah_id' => 'nullable|exists:surahs,id',
            'current_juz' => 'nullable|integer|min:1|max:30',
            'current_page' => 'nullable|integer|min:1|max:604',
            'notes' => 'nullable|string',
        ]);

        HifzEnrollment::create([
            ...$data,
            'hifz_program_id' => $program->id,
            'supervisor_id' => $data['supervisor_id'] ?? $program->supervisor_id,
            'teacher_id' => $data['teacher_id'] ?? $program->default_teacher_id,
        ]);

        return redirect()->route('hifz.programs.show', $program)
            ->with('success', trans('admin.hifz_flash_enrolled'));
    }
}
