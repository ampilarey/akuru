<?php

namespace App\Domains\Hifz\Http\Controllers;

use App\Domains\Hifz\Models\QuranProgress;
use App\Domains\People\Models\Student;
use App\Domains\People\Models\Teacher;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class QuranProgressController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->isStudent()) {
            $progress = $user->student
                ? $user->student->quranProgress()->with('teacher')->latest()->get()
                : collect();
        } elseif ($user->isTeacher()) {
            $progress = $user->teacher
                ? QuranProgress::where('teacher_id', $user->teacher->id)
                    ->with('student')
                    ->latest()
                    ->get()
                : collect();
        } else {
            $progress = QuranProgress::with(['student', 'teacher'])->latest()->get();
        }

        return view('quran-progress.index', compact('progress'));
    }

    public function create()
    {
        $students = Student::with('user')->get();
        $teachers = Teacher::with('user')->get();

        return view('quran-progress.create', compact('students', 'teachers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'teacher_id' => 'required|exists:teachers,id',
            'surah_name' => 'required|string',
            'surah_name_arabic' => 'required|string',
            'surah_number' => 'required|integer|min:1|max:114',
            'from_ayah' => 'nullable|integer|min:1',
            'to_ayah' => 'nullable|integer|min:1',
            'type' => 'required|in:memorization,recitation,revision',
            'status' => 'required|in:in_progress,completed,needs_revision',
            'accuracy_percentage' => 'nullable|integer|min:0|max:100',
            'teacher_notes' => 'nullable|string',
            'teacher_notes_arabic' => 'nullable|string',
        ]);

        QuranProgress::create($data);

        return redirect()->route('quran-progress.index')
            ->with('success', 'Quran progress recorded successfully!');
    }

    public function show(QuranProgress $quranProgress)
    {
        $quranProgress->load(['student.user', 'teacher.user']);

        return view('quran-progress.show', compact('quranProgress'));
    }

    public function edit(QuranProgress $quranProgress)
    {
        $students = Student::with('user')->get();
        $teachers = Teacher::with('user')->get();

        return view('quran-progress.edit', compact('quranProgress', 'students', 'teachers'));
    }

    public function update(Request $request, QuranProgress $quranProgress)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'teacher_id' => 'required|exists:teachers,id',
            'surah_name' => 'required|string',
            'surah_name_arabic' => 'required|string',
            'surah_number' => 'required|integer|min:1|max:114',
            'from_ayah' => 'nullable|integer|min:1',
            'to_ayah' => 'nullable|integer|min:1',
            'type' => 'required|in:memorization,recitation,revision',
            'status' => 'required|in:in_progress,completed,needs_revision',
            'accuracy_percentage' => 'nullable|integer|min:0|max:100',
            'teacher_notes' => 'nullable|string',
            'teacher_notes_arabic' => 'nullable|string',
        ]);

        $quranProgress->update($data);

        return redirect()->route('quran-progress.index')
            ->with('success', 'Quran progress updated successfully!');
    }

    public function destroy(QuranProgress $quranProgress)
    {
        $quranProgress->delete();

        return redirect()->route('quran-progress.index')
            ->with('success', 'Quran progress deleted successfully!');
    }

    /**
     * One pupil's Hifz progress — the tab the legacy Blade student record used
     * to carry. It lives here now because `quran_progress` is this module's
     * data (so the §52.27 flag covers it by namespace) and the People screen
     * that hosted it is gone; the React profile links to it instead.
     */
    public function student(Student $student)
    {
        $progress = $student->quranProgress()->with('teacher.user')->latest()->get();

        return view('students.quran-progress', compact('student', 'progress'));
    }

    public function updateProgress(Request $request, Student $student)
    {
        $data = $request->validate([
            'surah_name' => 'required|string',
            'surah_name_arabic' => 'required|string',
            'surah_number' => 'required|integer|min:1|max:114',
            'from_ayah' => 'nullable|integer|min:1',
            'to_ayah' => 'nullable|integer|min:1',
            'type' => 'required|in:memorization,recitation,revision',
            'status' => 'required|in:in_progress,completed,needs_revision',
            'accuracy_percentage' => 'nullable|integer|min:0|max:100',
            'teacher_notes' => 'nullable|string',
            'teacher_notes_arabic' => 'nullable|string',
        ]);

        $teacher = auth()->user()->teacher;
        if (! $teacher) {
            return redirect()->back()->withErrors(['teacher' => 'Teacher profile required to record progress.']);
        }

        // Set on the validated data rather than merged back into the request.
        // This used to `merge()` and then write `$request->all()`; writing the
        // validated array instead means anything merged after validation would
        // silently not be written, so the two ids are put where the write can
        // see them.
        //
        // Note this is the **best** scoped of the three: the student comes from
        // the route binding and the teacher from the signed-in user, rather
        // than from the request body as `store()` and `update()` take them.
        $data['student_id'] = $student->id;
        $data['teacher_id'] = $teacher->id;

        QuranProgress::create($data);

        return redirect()->back()
            ->with('success', 'Quran progress updated successfully!');
    }
}
