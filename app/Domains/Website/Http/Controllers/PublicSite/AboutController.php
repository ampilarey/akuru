<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\HR\Models\Instructor;
use App\Domains\Website\Models\Page;
use App\Domains\Website\Models\Testimonial;
use App\Http\Controllers\Controller;

class AboutController extends Controller
{
    public function index()
    {
        $page = Page::where('slug', 'about')->where('is_published', true)->first();

        $stats = [
            'students' => max(CourseEnrollment::count(), 500),
            'courses' => max(Course::where('status', '!=', 'draft')->count(), 12),
            'teachers' => max(Instructor::where('is_active', true)->count(), 8),
            'years' => 5,
        ];

        $instructors = Instructor::where('is_active', true)
            ->orderBy('sort_order')
            ->take(8)
            ->get();

        // Testimonial does not share Instructor's column names — it has
        // `is_public` and `order`, not `is_active` and `sort_order`. The
        // Instructor query shape above was copied onto it, and the resulting
        // "Unknown column 'is_active'" made **the public About page 500 for
        // every visitor**. Use the model's own scopes, as
        // ListCoursePageTestimonialsAction already does.
        $testimonials = Testimonial::query()->public()->ordered()->take(6)->get();

        return view('public.about.index', compact('page', 'stats', 'instructors', 'testimonials'));
    }
}
