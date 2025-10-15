<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subject;
use App\Models\Student;

class ELearningController extends Controller
{
    public function index()
    {
        $subjects = Subject::where('is_active', true)->get();
        return view('e-learning.index', compact('subjects'));
    }
    
    public function show(Subject $subject)
    {
        $user = auth()->user();
        
        // Check if user has access to this subject
        if ($user->isStudent()) {
            $student = $user->student;
            if (!$student || !$student->classRoom->subjects->contains($subject)) {
                abort(403, 'You do not have access to this subject.');
            }
        }
        
        return view('e-learning.show', compact('subject'));
    }
    
    public function quranLessons()
    {
        $quranSubjects = Subject::where('is_quran_subject', true)
            ->where('is_active', true)
            ->get();
            
        return view('e-learning.quran-lessons', compact('quranSubjects'));
    }
    
    public function arabicLessons()
    {
        $arabicSubjects = Subject::where('type', 'Arabic')
            ->where('is_active', true)
            ->get();
            
        return view('e-learning.arabic-lessons', compact('arabicSubjects'));
    }
    
    public function islamicStudies()
    {
        $islamicSubjects = Subject::where('type', 'Islamic Studies')
            ->where('is_active', true)
            ->get();
            
        return view('e-learning.islamic-studies', compact('islamicSubjects'));
    }
}
