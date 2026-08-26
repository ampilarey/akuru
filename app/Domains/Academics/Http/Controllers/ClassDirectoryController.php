<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\ListClassRosterAction;
use App\Domains\Academics\Actions\ResolveDefaultSchoolIdAction;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\People\Actions\ListClassTeacherOptionsAction;
use App\Domains\People\Actions\SearchRosterCandidatesAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClassDirectoryController extends Controller
{
    public function index(Request $request): Response
    {
        $yearId = $request->integer('academic_year_id') ?: AcademicYear::query()
            ->where('status', 'active')
            ->value('id');

        $teachers = app(ListClassTeacherOptionsAction::class)->execute();
        $teacherNames = $teachers->pluck('name', 'id');

        $classes = ClassRoom::query()
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
            ->orderBy('name')
            ->get()
            ->map(fn (ClassRoom $class) => [
                'id' => $class->id,
                'name' => $class->name,
                'section' => $class->section,
                'capacity' => $class->capacity,
                'academic_year_id' => $class->academic_year_id,
                'class_teacher_id' => $class->class_teacher_id,
                'class_teacher_name' => $class->class_teacher_id
                    ? ($teacherNames[$class->class_teacher_id] ?? null)
                    : null,
            ]);

        return Inertia::render('Academics/Classes/Index', [
            'yearId' => $yearId,
            'years' => AcademicYear::query()->orderByDesc('start_date')->get(['id', 'name', 'status']),
            'classes' => $classes,
            'teachers' => $teachers->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->input('class_teacher_id') === '') {
            $request->merge(['class_teacher_id' => null]);
        }

        $data = $request->validate([
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('classes')->where(fn ($query) => $query
                    ->where('academic_year_id', $request->integer('academic_year_id'))
                    ->where('section', $request->input('section') ?? '')),
            ],
            'section' => ['nullable', 'string', 'max:64'],
            'level' => ['required', 'string', 'max:64'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'class_teacher_id' => ['nullable', 'exists:users,id'],
        ], [
            'name.unique' => 'A class with this name and section already exists for this year.',
        ]);

        $data['school_id'] = app(ResolveDefaultSchoolIdAction::class)->execute();
        $data['is_active'] = true;

        ClassRoom::query()->create($data);

        return redirect()
            ->route('academics.classes.index', ['academic_year_id' => $data['academic_year_id']])
            ->with('success', 'Class created.');
    }

    public function show(Request $request, ClassRoom $classRoom): Response
    {
        $query = trim($request->string('q')->toString());
        $teacher = $classRoom->class_teacher_id
            ? app(ListClassTeacherOptionsAction::class)->execute()->firstWhere('id', $classRoom->class_teacher_id)
            : null;

        return Inertia::render('Academics/Classes/Show', [
            'classRoom' => [
                'id' => $classRoom->id,
                'name' => $classRoom->name,
                'section' => $classRoom->section,
                'capacity' => $classRoom->capacity,
                'academic_year_id' => $classRoom->academic_year_id,
                'class_teacher_id' => $classRoom->class_teacher_id,
                'class_teacher_name' => is_array($teacher) ? ($teacher['name'] ?? null) : null,
            ],
            'roster' => app(ListClassRosterAction::class)->execute($classRoom->id),
            'q' => $query,
            'candidates' => app(SearchRosterCandidatesAction::class)->execute($query),
        ]);
    }

    public function assign(Request $request, ClassRoom $classRoom): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
        ]);

        app(AssignStudentToClassAction::class)->execute($classRoom, (int) $data['student_id']);

        return redirect()
            ->route('academics.classes.show', $classRoom)
            ->with('success', 'Student assigned.');
    }
}
