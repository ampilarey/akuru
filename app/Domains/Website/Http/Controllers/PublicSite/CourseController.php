<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\Courses\Actions\ComposeCourseConversionSignalsAction;
use App\Domains\Courses\Actions\ComposeCoursePageCtaAction;
use App\Domains\Courses\Actions\PresentCourseLearningOutcomesAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseCategory;
use App\Domains\Website\Actions\CaptureCourseLeadAction;
use App\Domains\Website\Actions\JoinCourseWaitlistAction;
use App\Domains\Website\Actions\ListCoursePageTestimonialsAction;
use App\Domains\Website\Enums\LeadSource;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with('category');

        // Filter by category
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->openForPublicListing();
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
            $query->where(function ($q) use ($searchTerm) {
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

        if ($request->input('status') === 'open') {
            $query->where(function ($q) {
                $q->whereNull('enrollment_deadline')
                    ->orWhereDate('enrollment_deadline', '>=', now()->timezone(config('app.timezone'))->toDateString());
            });
        }

        $courses = $query->paginate(12)->withQueryString();
        $this->attachConversion($courses->getCollection());

        $categories = CourseCategory::ordered()->get();

        // Get featured courses for sidebar
        $featuredCourses = Course::featured()
            ->openForPublicListing()
            ->take(3)
            ->get();
        $this->attachConversion($featuredCourses);

        return view('public.courses.index', compact('courses', 'categories', 'featuredCourses'));
    }

    public function show(Course $course)
    {
        $course->load('category', 'admissionApplications', 'instructors');

        // Related courses from same category
        $relatedCourses = Course::where('course_category_id', $course->course_category_id)
            ->where('id', '!=', $course->id)
            ->openForPublicListing()
            ->take(3)
            ->get();

        // Featured courses for sidebar
        $featuredCourses = Course::featured()
            ->where('id', '!=', $course->id)
            ->openForPublicListing()
            ->take(3)
            ->get();

        // Recent courses
        $recentCourses = Course::where('id', '!=', $course->id)
            ->openForPublicListing()
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get();

        $this->attachConversion(collect([$course, ...$relatedCourses, ...$featuredCourses, ...$recentCourses]));

        $outcomes = app(PresentCourseLearningOutcomesAction::class)->execute((int) $course->id, app()->getLocale());
        $testimonials = app(ListCoursePageTestimonialsAction::class)->execute((int) $course->id);
        $cta = app(ComposeCoursePageCtaAction::class)->execute((int) $course->id);

        return view('public.courses.show', compact('course', 'relatedCourses', 'featuredCourses', 'recentCourses', 'outcomes', 'testimonials', 'cta'));
    }

    public function syllabus(Request $request, Course $course): RedirectResponse
    {
        if ($request->filled('website')) {
            return back()->with('success', 'Syllabus is on its way.');
        }

        $cta = app(ComposeCoursePageCtaAction::class)->execute((int) $course->id);
        if (($cta['syllabus']['url'] ?? null) === null) {
            throw ValidationException::withMessages([
                'course' => 'A syllabus is not available for this course.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        app(CaptureCourseLeadAction::class)->execute((int) $course->id, LeadSource::Syllabus, $data);

        return back()
            ->with('success', 'Syllabus is ready — download the PDF below.')
            ->with('syllabus_url', $cta['syllabus']['url']);
    }

    public function waitlist(Request $request, Course $course): RedirectResponse
    {
        if ($request->filled('website')) {
            return back()->with('success', 'Thanks — we will contact you if a seat opens.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        app(JoinCourseWaitlistAction::class)->execute((int) $course->id, [
            ...$data,
            'message' => 'Waiting list for '.$course->title,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'You are on the waiting list. We will contact you if a seat opens.');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Course>  $courses
     */
    private function attachConversion(\Illuminate\Support\Collection $courses): void
    {
        $signals = app(ComposeCourseConversionSignalsAction::class)->forCourses($courses->unique('id')->values());
        foreach ($courses as $course) {
            $course->setAttribute('conversion', $signals[$course->id] ?? null);
        }
    }
}
