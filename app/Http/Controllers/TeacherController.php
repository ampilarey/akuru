<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Teacher;
use App\Models\User;
use App\Models\School;
use App\Models\Subject;

class TeacherController extends Controller
{
    public function index()
    {
        $teachers = Teacher::with(['user', 'school', 'subjects'])->latest()->get();
        return view('teachers.index', compact('teachers'));
    }
    
    public function create()
    {
        $schools = School::all();
        $subjects = Subject::all();
        return view('teachers.create', compact('schools', 'subjects'));
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
            'teacher_id' => 'required|string|unique:teachers',
            'school_id' => 'required|exists:schools,id',
            'joining_date' => 'required|date',
            'salary' => 'nullable|numeric|min:0',
            'qualification' => 'required|string',
            'qualification_arabic' => 'nullable|string',
            'qualification_dhivehi' => 'nullable|string',
            'specialization' => 'required|string',
            'specialization_arabic' => 'nullable|string',
            'specialization_dhivehi' => 'nullable|string',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'first_name_arabic' => 'nullable|string',
            'last_name_arabic' => 'nullable|string',
            'first_name_dhivehi' => 'nullable|string',
            'last_name_dhivehi' => 'nullable|string',
            'subjects' => 'nullable|array',
            'subjects.*' => 'exists:subjects,id',
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
        
        $user->assignRole('teacher');
        
        // Create teacher profile
        $teacher = Teacher::create([
            'user_id' => $user->id,
            'school_id' => $request->school_id,
            'teacher_id' => $request->teacher_id,
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
            'email' => $request->email,
            'qualification' => $request->qualification,
            'qualification_arabic' => $request->qualification_arabic,
            'qualification_dhivehi' => $request->qualification_dhivehi,
            'specialization' => $request->specialization,
            'specialization_arabic' => $request->specialization_arabic,
            'specialization_dhivehi' => $request->specialization_dhivehi,
            'joining_date' => $request->joining_date,
            'salary' => $request->salary,
            'status' => 'active',
        ]);
        
        // Attach subjects if provided
        if ($request->subjects) {
            $teacher->subjects()->attach($request->subjects);
        }
        
        return redirect()->route('teachers.index')
            ->with('success', 'Teacher created successfully!');
    }
    
    public function show(Teacher $teacher)
    {
        $teacher->load(['user', 'school', 'subjects', 'classes', 'quranProgress.student.user', 'grades.student.user']);
        return view('teachers.show', compact('teacher'));
    }
    
    public function edit(Teacher $teacher)
    {
        $schools = School::all();
        $subjects = Subject::all();
        $teacher->load('user');
        return view('teachers.edit', compact('teacher', 'schools', 'subjects'));
    }
    
    public function update(Request $request, Teacher $teacher)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $teacher->user_id,
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female',
            'national_id' => 'nullable|string',
            'teacher_id' => 'required|string|unique:teachers,teacher_id,' . $teacher->id,
            'school_id' => 'required|exists:schools,id',
            'joining_date' => 'required|date',
            'salary' => 'nullable|numeric|min:0',
            'qualification' => 'required|string',
            'qualification_arabic' => 'nullable|string',
            'qualification_dhivehi' => 'nullable|string',
            'specialization' => 'required|string',
            'specialization_arabic' => 'nullable|string',
            'specialization_dhivehi' => 'nullable|string',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'first_name_arabic' => 'nullable|string',
            'last_name_arabic' => 'nullable|string',
            'first_name_dhivehi' => 'nullable|string',
            'last_name_dhivehi' => 'nullable|string',
            'subjects' => 'nullable|array',
            'subjects.*' => 'exists:subjects,id',
        ]);
        
        // Update user account
        $teacher->user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'national_id' => $request->national_id,
        ]);
        
        // Update teacher profile
        $teacher->update([
            'school_id' => $request->school_id,
            'teacher_id' => $request->teacher_id,
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
            'email' => $request->email,
            'qualification' => $request->qualification,
            'qualification_arabic' => $request->qualification_arabic,
            'qualification_dhivehi' => $request->qualification_dhivehi,
            'specialization' => $request->specialization,
            'specialization_arabic' => $request->specialization_arabic,
            'specialization_dhivehi' => $request->specialization_dhivehi,
            'joining_date' => $request->joining_date,
            'salary' => $request->salary,
        ]);
        
        // Update subjects
        if ($request->subjects) {
            $teacher->subjects()->sync($request->subjects);
        } else {
            $teacher->subjects()->detach();
        }
        
        return redirect()->route('teachers.index')
            ->with('success', 'Teacher updated successfully!');
    }
    
    public function destroy(Teacher $teacher)
    {
        $teacher->user->delete(); // This will also delete the teacher due to cascade
        return redirect()->route('teachers.index')
            ->with('success', 'Teacher deleted successfully!');
    }
}