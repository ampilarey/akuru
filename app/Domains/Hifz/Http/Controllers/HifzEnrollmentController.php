<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\People\Models\Student;
use App\Domains\People\Models\Teacher;
use App\Domains\Hifz\Services\HifzScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HifzEnrollmentController extends Controller
{
    public function __construct(protected HifzScopeService $scope) {}

    public function index(HifzProgram $program): View
    {
        $this->authorize('view', $program);

        $enrollments = $program->enrollments()->with(['student.user', 'teacher.user'])->paginate(20);

        return view('hifz.enrollments.index', compact('program', 'enrollments'));
    }

    public function create(HifzProgram $program): View
    {
        $this->authorize('update', $program);

        return view('hifz.enrollments.create', [
            'program' => $program,
            'students' => Student::with('user')->orderBy('first_name')->get(),
            'teachers' => Teacher::with('user')->get(),
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
            ->with('success', 'Student enrolled successfully.');
    }
}
