<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\{Course, CourseCategory};
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with('category');
        
        // Filter by category
        if ($request->filled('category')) {
            $query->whereHas('category', function($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }
        
        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Default to open and upcoming courses
            $query->whereIn('status', ['open', 'upcoming']);
        }
        
        // Filter by language
        if ($request->filled('language')) {
            $query->byLanguage($request->language);
        }
        
        // Filter by level
        if ($request->filled('level')) {
            $query->byLevel($request->level);
        }
        
        $courses = $query->orderBy('status')
                        ->orderBy('title')
                        ->paginate(12);
        
        $categories = CourseCategory::ordered()->get();
        
        return view('public.courses.index', compact('courses', 'categories'));
    }
    
    public function show(Course $course)
    {
        $course->load('category', 'admissionApplications');
        
        // Related courses from same category
        $relatedCourses = Course::where('course_category_id', $course->course_category_id)
            ->where('id', '!=', $course->id)
            ->whereIn('status', ['open', 'upcoming'])
            ->take(3)
            ->get();
        
        return view('public.courses.show', compact('course', 'relatedCourses'));
    }
}
