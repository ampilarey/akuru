<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\User;
use App\Models\ClassRoom;
use App\Models\ParentGuardian;

class StudentController extends Controller
{
    public function index()
    {
        $students = Student::with(['user', 'classRoom', 'parentGuardians'])->latest()->get();
        return view('students.index', compact('students'));
    }
    
    public function create()
    {
        $classes = ClassRoom::all();
        $parents = ParentGuardian::with('user')->get();
        return view('students.create', compact('classes', 'parents'));
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female',
            'national_id' => 'nullable|string',
            'student_id' => 'required|string|unique:students',
            'class_id' => 'required|exists:classes,id',
            'admission_date' => 'required|date',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'first_name_arabic' => 'nullable|string',
            'last_name_arabic' => 'nullable|string',
            'first_name_dhivehi' => 'nullable|string',
            'last_name_dhivehi' => 'nullable|string',
        ]);
        
        // Create user account
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'phone' => $request->phone,
            'address' => $request->address,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'national_id' => $request->national_id,
            'is_active' => true,
        ]);
        
        $user->assignRole('student');
        
        // Create student profile
        $student = Student::create([
            'user_id' => $user->id,
            'school_id' => 1, // Assuming single school for now
            'class_id' => $request->class_id,
            'student_id' => $request->student_id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'first_name_arabic' => $request->first_name_arabic,
            'last_name_arabic' => $request->last_name_arabic,
            'first_name_dhivehi' => $request->first_name_dhivehi,
            'last_name_dhivehi' => $request->last_name_dhivehi,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'national_id' => $request->national_id,
            'phone' => $request->phone,
            'address' => $request->address,
            'admission_date' => $request->admission_date,
            'status' => 'active',
        ]);
        
        return redirect()->route('students.index')
            ->with('success', 'Student created successfully!');
    }
    
    public function show(Student $student)
    {
        $student->load(['user', 'classRoom', 'parentGuardians.user', 'quranProgress.teacher.user', 'grades.subject']);
        return view('students.show', compact('student'));
    }
    
    public function edit(Student $student)
    {
        $classes = ClassRoom::all();
        $parents = ParentGuardian::with('user')->get();
        $student->load('user');
        return view('students.edit', compact('student', 'classes', 'parents'));
    }
    
    public function update(Request $request, Student $student)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $student->user_id,
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female',
            'national_id' => 'nullable|string',
            'student_id' => 'required|string|unique:students,student_id,' . $student->id,
            'class_id' => 'required|exists:classes,id',
            'admission_date' => 'required|date',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'first_name_arabic' => 'nullable|string',
            'last_name_arabic' => 'nullable|string',
            'first_name_dhivehi' => 'nullable|string',
            'last_name_dhivehi' => 'nullable|string',
        ]);
        
        // Update user account
        $student->user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'national_id' => $request->national_id,
        ]);
        
        // Update student profile
        $student->update([
            'class_id' => $request->class_id,
            'student_id' => $request->student_id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'first_name_arabic' => $request->first_name_arabic,
            'last_name_arabic' => $request->last_name_arabic,
            'first_name_dhivehi' => $request->first_name_dhivehi,
            'last_name_dhivehi' => $request->last_name_dhivehi,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'national_id' => $request->national_id,
            'phone' => $request->phone,
            'address' => $request->address,
            'admission_date' => $request->admission_date,
        ]);
        
        return redirect()->route('students.index')
            ->with('success', 'Student updated successfully!');
    }
    
    public function destroy(Student $student)
    {
        $student->user->delete(); // This will also delete the student due to cascade
        return redirect()->route('students.index')
            ->with('success', 'Student deleted successfully!');
    }
    
    public function quranProgress(Student $student)
    {
        $progress = $student->quranProgress()->with('teacher.user')->latest()->get();
        return view('students.quran-progress', compact('student', 'progress'));
    }
}
