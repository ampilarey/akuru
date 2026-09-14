<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Actions\AssignClassTeacherAction;
use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\ListClassRosterAction;
use App\Domains\Academics\Actions\ResolveDefaultSchoolIdAction;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Courses\Actions\ListClassroomAssessmentsAction;
use App\Domains\People\Actions\ListClassTeacherOptionsAction;
use App\Domains\People\Actions\SearchRosterCandidatesAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClassDirectoryController extends Controller
{
    /**
     * CLAUDE.md: *"every listing gets CSV export."* This list is how a school
     * checks its own shape at the start of a year — which classes exist, who
     * teaches each, and how many places each holds.
     *
     * Scoped to the year on screen, so the export matches what was being
     * looked at rather than every class the school has ever had.
     */
    public function export(Request $request): StreamedResponse
    {
        $yearId = $request->integer('academic_year_id') ?: AcademicYear::query()
            ->where('status', 'active')
            ->value('id');

        // Naming, not choosing: a class whose teacher has since left still
        // needs that teacher's name in the export.
        $teacherNames = app(ListClassTeacherOptionsAction::class)->everyone()->pluck('name', 'id');

        $classes = ClassRoom::query()
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
            ->orderBy('name')
            ->get();

        return response()->streamDownload(function () use ($classes, $teacherNames): void {
            $handle = fopen('php://output', 'w');
            Csv::put($handle, ['id', 'name', 'section', 'capacity', 'class_teacher']);

            foreach ($classes as $class) {
                Csv::put($handle, [
                    $class->id,
                    $class->name,
                    $class->section,
                    $class->capacity,
                    $class->class_teacher_id ? ($teacherNames[$class->class_teacher_id] ?? '') : '',
                ]);
            }

            fclose($handle);
        }, 'classes.csv', ['Content-Type' => 'text/csv']);
    }

    public function index(Request $request): Response
    {
        $yearId = $request->integer('academic_year_id') ?: AcademicYear::query()
            ->where('status', 'active')
            ->value('id');

        $classes = ClassRoom::query()
            ->when($yearId, fn ($query) => $query->where('academic_year_id', $yearId))
            ->orderBy('name')
            ->get();

        // Names for every class on screen, including teachers who have left —
        // and a picker that offers the ones who have not, plus whoever each
        // class already has, so opening the page cannot silently drop an
        // assignment on the next save.
        $options = app(ListClassTeacherOptionsAction::class);
        $teacherNames = $options->everyone()->pluck('name', 'id');
        $teachers = $options->assignable($classes->pluck('class_teacher_id'));

        $classes = $classes
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

    public function update(Request $request, ClassRoom $classRoom): RedirectResponse
    {
        if ($request->input('class_teacher_id') === '') {
            $request->merge(['class_teacher_id' => null]);
        }

        $data = $request->validate([
            'class_teacher_id' => ['nullable', 'exists:users,id'],
        ]);

        app(AssignClassTeacherAction::class)->execute(
            $classRoom,
            $data['class_teacher_id'] !== null ? (int) $data['class_teacher_id'] : null,
        );

        return redirect()
            ->route('academics.classes.show', $classRoom)
            ->with('success', 'Class teacher updated.');
    }

    public function show(Request $request, ClassRoom $classRoom): Response
    {
        $query = trim($request->string('q')->toString());
        $options = app(ListClassTeacherOptionsAction::class);
        // The picker keeps this class's current teacher whatever became of
        // them; the name is looked up from the full list so a leaver is still
        // named rather than blanked.
        $teachers = $options->assignable([$classRoom->class_teacher_id]);
        $teacher = $classRoom->class_teacher_id
            ? $options->everyone()->firstWhere('id', $classRoom->class_teacher_id)
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
            'teachers' => $teachers->all(),
            'roster' => app(ListClassRosterAction::class)->execute($classRoom->id),
            'assessments' => app(ListClassroomAssessmentsAction::class)->execute($classRoom->id)->values(),
            'q' => $query,
            'candidates' => app(SearchRosterCandidatesAction::class)->execute($query),
        ]);
    }

    public function exportAssessments(ClassRoom $classRoom): StreamedResponse
    {
        $rows = app(ListClassroomAssessmentsAction::class)->execute($classRoom->id);

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            Csv::put($handle, ['id', 'title', 'assessment_type', 'status', 'max_score', 'legacy_quiz_id', 'legacy_assignment_id']);
            foreach ($rows as $row) {
                Csv::put($handle, [
                    $row['id'],
                    $row['title'],
                    $row['assessment_type'],
                    $row['status'],
                    $row['max_score'],
                    $row['legacy_quiz_id'],
                    $row['legacy_assignment_id'],
                ]);
            }
            fclose($handle);
        }, 'class-'.$classRoom->id.'-assessments.csv', ['Content-Type' => 'text/csv']);
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
