<?php

namespace App\Http\Controllers\Admin\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\{Course, CourseCategory};
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::with('category')->orderBy('title')->paginate(15);
        return view('admin.public-site.courses.index', compact('courses'));
    }

    public function create()
    {
        $categories = CourseCategory::ordered()->get();
        return view('admin.public-site.courses.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_category_id' => 'required|exists:course_categories,id',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:courses',
            'short_desc' => 'required|string',
            'body' => 'required|string',
            'cover_image' => 'required|string|max:255',
            'language' => 'required|in:en,ar,dv,mixed',
            'level' => 'required|in:kids,youth,adult,all',
            'fee' => 'nullable|numeric|min:0',
            'status' => 'required|in:open,closed,upcoming',
            'seats' => 'nullable|integer|min:1',
        ]);

        Course::create($validated);

        return redirect()->route('admin.courses.index')
                        ->with('success', 'Course created successfully.');
    }

    public function edit(Course $course)
    {
        $categories = CourseCategory::ordered()->get();
        return view('admin.public-site.courses.edit', compact('course', 'categories'));
    }

    public function update(Request $request, Course $course)
    {
        $validated = $request->validate([
            'course_category_id' => 'required|exists:course_categories,id',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:courses,slug,' . $course->id,
            'short_desc' => 'required|string',
            'body' => 'required|string',
            'cover_image' => 'required|string|max:255',
            'language' => 'required|in:en,ar,dv,mixed',
            'level' => 'required|in:kids,youth,adult,all',
            'fee' => 'nullable|numeric|min:0',
            'status' => 'required|in:open,closed,upcoming',
            'seats' => 'nullable|integer|min:1',
        ]);

        $course->update($validated);

        return redirect()->route('admin.courses.index')
                        ->with('success', 'Course updated successfully.');
    }

    public function destroy(Course $course)
    {
        $course->delete();

        return redirect()->route('admin.courses.index')
                        ->with('success', 'Course deleted successfully.');
    }
}
