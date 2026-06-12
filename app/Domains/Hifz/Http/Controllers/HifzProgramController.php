<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hifz\StoreHifzProgramRequest;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\People\Models\Teacher;
use App\Domains\Identity\Models\User;
use App\Domains\Hifz\Services\HifzScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HifzProgramController extends Controller
{
    public function __construct(protected HifzScopeService $scope) {}

    public function index(): View
    {
        $this->authorize('viewAny', HifzProgram::class);

        $user = auth()->user();
        $query = HifzProgram::with(['classRoom', 'supervisor', 'defaultTeacher.user']);

        if (! $user->isHifzDean() && ! $user->isAdminLevel()) {
            $query->whereIn('id', $this->scope->assignedProgramIds($user));
        }

        $programs = $query->latest()->paginate(15);

        return view('hifz.programs.index', compact('programs'));
    }

    public function create(): View
    {
        $this->authorize('create', HifzProgram::class);

        return view('hifz.programs.create', [
            'classes' => ClassRoom::orderBy('name')->get(),
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
            'supervisors' => User::role('supervisor')->get(),
            'deans' => User::role(['headmaster', 'admin'])->get(),
            'teachers' => Teacher::with('user')->get(),
        ]);
    }

    public function store(StoreHifzProgramRequest $request): RedirectResponse
    {
        $program = HifzProgram::create([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('hifz.programs.show', $program)
            ->with('success', 'Hifz program created successfully.');
    }

    public function show(HifzProgram $program): View
    {
        $this->authorize('view', $program);

        $program->load(['enrollments.student.user', 'supervisor', 'defaultTeacher.user', 'classRoom']);

        return view('hifz.programs.show', compact('program'));
    }

    public function edit(HifzProgram $program): View
    {
        $this->authorize('update', $program);

        return view('hifz.programs.edit', [
            'program' => $program,
            'classes' => ClassRoom::orderBy('name')->get(),
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
            'supervisors' => User::role('supervisor')->get(),
            'deans' => User::role(['headmaster', 'admin'])->get(),
            'teachers' => Teacher::with('user')->get(),
        ]);
    }

    public function update(StoreHifzProgramRequest $request, HifzProgram $program): RedirectResponse
    {
        $this->authorize('update', $program);

        $program->update([
            ...$request->validated(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()->route('hifz.programs.show', $program)
            ->with('success', 'Hifz program updated successfully.');
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

        return back()->with('success', 'Supervisor assigned successfully.');
    }
}
