<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\Period;
use App\Domains\Academics\Models\Subject;
use App\Domains\Academics\Models\SubstitutionAssignment;
use App\Domains\Academics\Models\SubstitutionRequest;
use App\Domains\Academics\Models\TeacherAbsence;
use App\Domains\People\Models\Teacher;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubstitutionRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = SubstitutionRequest::with([
            'absentTeacher.user',
            'subject',
            'classroom',
            'period',
            'assignment.substituteTeacher.user',
        ]);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }

        // Filter by teacher (for teacher view)
        if (auth()->user()->hasRole('teacher') && ! auth()->user()->hasAnyRole(['admin', 'headmaster', 'supervisor'])) {
            // Teachers can only see requests they can substitute for or are assigned to
            $teacher = auth()->user()->teacher;
            if ($teacher) {
                $query->where(function ($q) use ($teacher) {
                    $q->where('status', 'open')
                        ->orWhereHas('assignment', function ($subQuery) use ($teacher) {
                            $subQuery->where('substitute_teacher_id', $teacher->id);
                        });
                });
            }
        }

        $requests = $query->latest('date')->paginate(15);

        return view('substitutions.requests.index', compact('requests'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $teachers = Teacher::with('user')->get();
        $subjects = Subject::all();
        $classrooms = ClassRoom::all();
        $periods = Period::orderBy('start_time')->get();

        return view('substitutions.requests.create', compact('teachers', 'subjects', 'classrooms', 'periods'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'absent_teacher_id' => 'required|exists:teachers,id',
            'subject_id' => 'required|exists:subjects,id',
            'classroom_id' => 'required|exists:classes,id',
            'period_id' => 'required|exists:periods,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $substitutionRequest = SubstitutionRequest::create($validated);

        return redirect()
            ->route('substitutions.requests.index')
            ->with('success', 'Substitution request created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(SubstitutionRequest $request): View
    {
        $request->load([
            'absentTeacher.user',
            'subject',
            'classroom',
            'period',
            'assignment.substituteTeacher.user',
            'assignment.assignedBy',
        ]);

        return view('substitutions.requests.show', compact('request'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SubstitutionRequest $request): View
    {
        // Only allow editing if status is 'open'
        if ($request->status !== 'open') {
            abort(403, 'Cannot edit assigned or closed requests.');
        }

        $teachers = Teacher::with('user')->get();
        $subjects = Subject::all();
        $classrooms = ClassRoom::all();
        $periods = Period::orderBy('start_time')->get();

        return view('substitutions.requests.edit', compact('request', 'teachers', 'subjects', 'classrooms', 'periods'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SubstitutionRequest $substitutionRequest): RedirectResponse
    {
        // Only allow updating if status is 'open'
        if ($substitutionRequest->status !== 'open') {
            return redirect()
                ->route('substitutions.requests.index')
                ->with('error', 'Cannot update assigned or closed requests.');
        }

        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'absent_teacher_id' => 'required|exists:teachers,id',
            'subject_id' => 'required|exists:subjects,id',
            'classroom_id' => 'required|exists:classes,id',
            'period_id' => 'required|exists:periods,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $substitutionRequest->update($validated);

        return redirect()
            ->route('substitutions.requests.show', $substitutionRequest)
            ->with('success', 'Substitution request updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SubstitutionRequest $request): RedirectResponse
    {
        // Only allow deletion if status is 'open'
        if ($request->status !== 'open') {
            return redirect()
                ->route('substitutions.requests.index')
                ->with('error', 'Cannot delete assigned or closed requests.');
        }

        $request->delete();

        return redirect()
            ->route('substitutions.requests.index')
            ->with('success', 'Substitution request deleted successfully.');
    }

    /**
     * Allow a teacher to take a substitution request
     */
    public function take(Request $request, SubstitutionRequest $substitutionRequest): RedirectResponse
    {
        // Check if user is a teacher
        if (! auth()->user()->hasRole('teacher')) {
            abort(403, 'Only teachers can take substitution requests.');
        }

        // Check if request can be taken
        if (! $substitutionRequest->canBeTaken()) {
            return redirect()
                ->route('substitutions.requests.index')
                ->with('error', 'This substitution request cannot be taken.');
        }

        $teacher = auth()->user()->teacher;
        if (! $teacher) {
            return redirect()
                ->route('substitutions.requests.index')
                ->with('error', 'Teacher profile not found.');
        }

        // Create the assignment
        SubstitutionAssignment::create([
            'substitution_request_id' => $substitutionRequest->id,
            'substitute_teacher_id' => $teacher->id,
            'assigned_by' => auth()->id(),
            'assigned_at' => now(),
            'notes' => $request->input('notes'),
        ]);

        return redirect()
            ->route('substitutions.requests.index')
            ->with('success', 'Substitution request taken successfully.');
    }

    /**
     * Assign a substitution request to a specific teacher (admin/supervisor only)
     */
    public function assign(Request $request, SubstitutionRequest $substitutionRequest): RedirectResponse
    {
        // Check permissions
        if (! auth()->user()->hasAnyRole(['admin', 'headmaster', 'supervisor'])) {
            abort(403, 'Insufficient permissions to assign substitutions.');
        }

        $validated = $request->validate([
            'substitute_teacher_id' => 'required|exists:teachers,id',
            'notes' => 'nullable|string|max:500',
        ]);

        // Check if request can be assigned
        if ($substitutionRequest->status !== 'open') {
            return redirect()
                ->route('substitutions.requests.index')
                ->with('error', 'This substitution request is not available for assignment.');
        }

        // Create the assignment
        SubstitutionAssignment::create([
            'substitution_request_id' => $substitutionRequest->id,
            'substitute_teacher_id' => $validated['substitute_teacher_id'],
            'assigned_by' => auth()->id(),
            'assigned_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('substitutions.requests.index')
            ->with('success', 'Substitution request assigned successfully.');
    }

    public function absencesIndex(): View
    {
        $absences = TeacherAbsence::with(['teacher.user', 'creator'])
            ->latest()
            ->paginate(15);

        return view('substitutions.absences.index', compact('absences'));
    }

    public function absencesCreate(): View
    {
        $teachers = Teacher::with('user')->orderBy('first_name')->get();

        return view('substitutions.absences.create', compact('teachers'));
    }

    public function absencesStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'required|string|max:1000',
            'note' => 'nullable|string|max:1000',
        ]);

        TeacherAbsence::create([
            ...$validated,
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('absences.index')
            ->with('success', 'Teacher absence recorded.');
    }

    public function absencesEdit(TeacherAbsence $absence): View
    {
        $teachers = Teacher::with('user')->orderBy('first_name')->get();

        return view('substitutions.absences.edit', compact('absence', 'teachers'));
    }

    public function absencesUpdate(Request $request, TeacherAbsence $absence): RedirectResponse
    {
        $validated = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'required|string|max:1000',
            'note' => 'nullable|string|max:1000',
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $absence->update($validated);

        return redirect()
            ->route('absences.index')
            ->with('success', 'Teacher absence updated.');
    }

    public function absencesDestroy(TeacherAbsence $absence): RedirectResponse
    {
        $absence->delete();

        return redirect()
            ->route('absences.index')
            ->with('success', 'Teacher absence removed.');
    }
}
