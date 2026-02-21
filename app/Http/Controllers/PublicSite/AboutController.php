<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\{Course, CourseEnrollment, Instructor, Testimonial, Page};

class AboutController extends Controller
{
    public function index()
    {
        $page = Page::where('slug', 'about')->where('is_published', true)->first();

        $stats = [
            'students'  => max(CourseEnrollment::count(), 500),
            'courses'   => max(Course::where('status', '!=', 'draft')->count(), 12),
            'teachers'  => max(Instructor::where('is_active', true)->count(), 8),
            'years'     => 5,
        ];

        $instructors = Instructor::where('is_active', true)
            ->orderBy('sort_order')
            ->take(8)
            ->get();

        $testimonials = Testimonial::where('is_active', true)
            ->orderBy('sort_order')
            ->take(6)
            ->get();

        return view('public.about.index', compact('page', 'stats', 'instructors', 'testimonials'));
    }
}
