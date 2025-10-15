<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QuranProgress;
use App\Models\Student;
use App\Models\Teacher;

class QuranProgressController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        if ($user->isStudent()) {
            $progress = $user->student->quranProgress()->with('teacher')->latest()->get();
        } elseif ($user->isTeacher()) {
            $progress = QuranProgress::where('teacher_id', $user->teacher->id)
                ->with('student')
                ->latest()
                ->get();
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
        $request->validate([
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
        
        QuranProgress::create($request->all());
        
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
        $request->validate([
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
        
        $quranProgress->update($request->all());
        
        return redirect()->route('quran-progress.index')
            ->with('success', 'Quran progress updated successfully!');
    }
    
    public function destroy(QuranProgress $quranProgress)
    {
        $quranProgress->delete();
        
        return redirect()->route('quran-progress.index')
            ->with('success', 'Quran progress deleted successfully!');
    }
    
    public function updateProgress(Request $request, Student $student)
    {
        $request->validate([
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
        
        $request->merge([
            'student_id' => $student->id,
            'teacher_id' => auth()->user()->teacher->id,
        ]);
        
        QuranProgress::create($request->all());
        
        return redirect()->back()
            ->with('success', 'Quran progress updated successfully!');
    }
}
