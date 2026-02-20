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
        
        // Filter by featured courses
        if ($request->filled('featured')) {
            $query->featured();
        }
        
        // Filter by enrollment status
        if ($request->filled('enrollment')) {
            if ($request->enrollment === 'open') {
                $query->available();
            } elseif ($request->enrollment === 'upcoming') {
                $query->upcoming();
            }
        }
        
        // Search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('short_desc', 'like', "%{$searchTerm}%")
                  ->orWhere('body', 'like', "%{$searchTerm}%");
            });
        }
        
        // Sort options
        $sortBy = $request->get('sort', 'default');
        switch ($sortBy) {
            case 'title':
                $query->orderBy('title');
                break;
            case 'fee_low':
                $query->orderBy('fee', 'asc');
                break;
            case 'fee_high':
                $query->orderBy('fee', 'desc');
                break;
            case 'start_date':
                $query->orderBy('start_date', 'asc');
                break;
            case 'featured':
                $query->orderBy('is_featured', 'desc')->orderBy('title');
                break;
            default:
                $query->ordered();
        }
        
        $courses = $query->paginate(12)->withQueryString();
        
        $categories = CourseCategory::ordered()->get();
        
        // Get featured courses for sidebar
        $featuredCourses = Course::featured()
                                ->whereIn('status', ['open', 'upcoming'])
                                ->take(3)
                                ->get();
        
        return view('public.courses.index', compact('courses', 'categories', 'featuredCourses'));
    }
    
    public function show(Course $course)
    {
        $course->load('category', 'admissionApplications', 'instructors');
        
        // Related courses from same category
        $relatedCourses = Course::where('course_category_id', $course->course_category_id)
            ->where('id', '!=', $course->id)
            ->whereIn('status', ['open', 'upcoming'])
            ->take(3)
            ->get();
        
        // Featured courses for sidebar
        $featuredCourses = Course::featured()
                                ->where('id', '!=', $course->id)
                                ->whereIn('status', ['open', 'upcoming'])
                                ->take(3)
                                ->get();
        
        // Recent courses
        $recentCourses = Course::where('id', '!=', $course->id)
                              ->whereIn('status', ['open', 'upcoming'])
                              ->orderBy('created_at', 'desc')
                              ->take(3)
                              ->get();
        
        return view('public.courses.show', compact('course', 'relatedCourses', 'featuredCourses', 'recentCourses'));
    }
}
