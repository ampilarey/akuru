<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Domains\People\Models\Student;
use App\Domains\People\Models\Teacher;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A programme's enrolments. Inertia since the Hifz port's first slice
 * (BACKLOG C1, STATUS §5jv); the gates and the defaults (the programme's
 * supervisor and teacher when the form names none) are as they were.
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
            ]);

        return Inertia::render('Hifz/Enrollments', [
            'program' => ['id' => $program->id, 'name' => $program->name],
            'enrollments' => $enrollments,
            'can_update' => auth()->user()->can('update', $program),
            't' => trans('admin'),
        ]);
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
            't' => trans('admin'),
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
