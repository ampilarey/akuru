<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Hifz\Models\HifzEnrollment;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Services\HifzScopeService;
use App\Domains\Identity\Models\User;
use App\Domains\People\Models\Teacher;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hifz\StoreHifzProgramRequest;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hifz programmes. Inertia since the Hifz port's first slice (BACKLOG C1,
 * STATUS §5jv): the same screens at the same addresses, in the one shell,
 * every string keyed EN/DV/AR. The scoping and the policies are as they
 * were — a dean sees every programme, anyone else the ones they are
 * assigned to (`HifzScopeService`).
 */
class HifzProgramController extends Controller
{
    public function __construct(protected HifzScopeService $scope) {}

    public function index(): Response
    {
        $this->authorize('viewAny', HifzProgram::class);

        $user = auth()->user();
        $query = HifzProgram::with(['classRoom', 'supervisor', 'defaultTeacher.user']);

        if (! $user->isHifzDean()) {
            $query->whereIn('id', $this->scope->assignedProgramIds($user));
        }

        $programs = $query->latest()->paginate(15)->through(fn (HifzProgram $program): array => [
            'id' => $program->id,
            'name' => $program->name,
            'class' => $program->classRoom?->name,
            'supervisor' => $program->supervisor?->name,
            'teacher' => $program->defaultTeacher?->full_name,
            'status' => $program->status?->value,
        ]);

        return Inertia::render('Hifz/Programs', [
            'programs' => $programs,
            'can_create' => $user->can('create', HifzProgram::class),
            't' => Phrases::once('admin'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', HifzProgram::class);

        return Inertia::render('Hifz/ProgramForm', ['program' => null, 't' => Phrases::once('admin')] + $this->options());
    }

    public function store(StoreHifzProgramRequest $request): RedirectResponse
    {
        $program = HifzProgram::create([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('hifz.programs.show', $program)
            ->with('success', trans('admin.hifz_flash_created'));
    }

    public function show(HifzProgram $program): Response
    {
        $this->authorize('view', $program);

        $program->load(['enrollments.student.user', 'enrollments.teacher.user', 'supervisor', 'defaultTeacher.user', 'classRoom']);
        $user = auth()->user();
        $canAssign = $user->can('assignSupervisor', HifzProgram::class) && $user->can('update', $program);

        return Inertia::render('Hifz/Program', [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'description' => $program->description,
                'status' => $program->status?->value,
                'supervisor_id' => $program->supervisor_id,
            ],
            'enrollments' => $program->enrollments->map(fn (HifzEnrollment $enrollment): array => [
                'id' => $enrollment->id,
                'student' => $enrollment->student?->full_name,
                'teacher' => $enrollment->teacher?->full_name,
                'status' => $enrollment->status?->value,
                'page' => $enrollment->current_page,
            ])->values(),
            'can_update' => $user->can('update', $program),
            'can_assign_supervisor' => $canAssign,
            'supervisors' => $canAssign ? $this->options()['supervisors'] : [],
            't' => Phrases::once('admin'),
        ]);
    }

    public function edit(HifzProgram $program): Response
    {
        $this->authorize('update', $program);

        return Inertia::render('Hifz/ProgramForm', [
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'description' => $program->description,
                'status' => $program->status?->value,
                'class_id' => $program->class_id,
                'supervisor_id' => $program->supervisor_id,
                'default_teacher_id' => $program->default_teacher_id,
            ],
            't' => Phrases::once('admin'),
        ] + $this->options());
    }

    public function update(StoreHifzProgramRequest $request, HifzProgram $program): RedirectResponse
    {
        $this->authorize('update', $program);

        $program->update([
            ...$request->validated(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('hifz.programs.show', $program)
            ->with('success', trans('admin.hifz_flash_updated'));
    }

    public function assignSupervisor(Request $request, HifzProgram $program): RedirectResponse
    {
        $this->authorize('assignSupervisor', HifzProgram::class);
        $this->authorize('update', $program);

        $request->validate(['supervisor_id' => 'required|exists:users,id']);

        $program->update([
            'supervisor_id' => $request->supervisor_id,
            'updated_by' => auth()->id(),
        ]);

        return back()->with('success', trans('admin.hifz_flash_supervisor_assigned'));
    }

    /**
     * The pickers a programme form offers: classes, years, supervisors,
     * deans and teachers, each as id and name.
     *
     * @return array<string, list<array{id: int, name: string}>>
     */
    private function options(): array
    {
        $pair = fn ($row, string $name): array => ['id' => (int) $row->id, 'name' => (string) $row->{$name}];

        return [
            'classes' => ClassRoom::orderBy('name')->get()->map(fn ($row) => $pair($row, 'name'))->values()->all(),
            'academic_years' => AcademicYear::orderByDesc('start_date')->get()->map(fn ($row) => $pair($row, 'name'))->values()->all(),
            'supervisors' => User::role('supervisor')->get()->map(fn ($row) => $pair($row, 'name'))->values()->all(),
            'deans' => User::role(['headmaster', 'admin'])->get()->map(fn ($row) => $pair($row, 'name'))->values()->all(),
            'teachers' => Teacher::with('user')->get()->map(fn ($row) => $pair($row, 'full_name'))->values()->all(),
        ];
    }
}
