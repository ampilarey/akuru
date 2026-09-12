<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\Courses\Actions\DeleteCourseAction;
use App\Domains\Courses\Actions\SaveCourseLearningOutcomesAction;
use App\Domains\Courses\Actions\SaveCoursePublicCtaAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseCategory;
use App\Http\Controllers\Controller;
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
            'whatsapp_number' => 'nullable|string|max:32',
            'syllabus_media_file_id' => 'nullable|integer|exists:media_files,id',
        ]);

        $course = Course::create($validated);
        app(SaveCourseLearningOutcomesAction::class)->execute((int) $course->id, [
            'en' => $request->input('learning_outcomes_en', ''),
            'dv' => $request->input('learning_outcomes_dv', ''),
            'ar' => $request->input('learning_outcomes_ar', ''),
        ]);
        app(SaveCoursePublicCtaAction::class)->execute(
            (int) $course->id,
            $request->input('whatsapp_number'),
            $request->input('syllabus_media_file_id'),
        );

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
            'slug' => 'required|string|max:255|unique:courses,slug,'.$course->id,
            'short_desc' => 'required|string',
            'body' => 'required|string',
            'cover_image' => 'required|string|max:255',
            'language' => 'required|in:en,ar,dv,mixed',
            'level' => 'required|in:kids,youth,adult,all',
            'fee' => 'nullable|numeric|min:0',
            'status' => 'required|in:open,closed,upcoming',
            'seats' => 'nullable|integer|min:1',
            'whatsapp_number' => 'nullable|string|max:32',
            'syllabus_media_file_id' => 'nullable|integer|exists:media_files,id',
        ]);

        $course->update($validated);
        app(SaveCourseLearningOutcomesAction::class)->execute((int) $course->id, [
            'en' => $request->input('learning_outcomes_en', ''),
            'dv' => $request->input('learning_outcomes_dv', ''),
            'ar' => $request->input('learning_outcomes_ar', ''),
        ]);
        app(SaveCoursePublicCtaAction::class)->execute(
            (int) $course->id,
            $request->input('whatsapp_number'),
            $request->input('syllabus_media_file_id'),
        );

        return redirect()->route('admin.courses.index')
            ->with('success', 'Course updated successfully.');
    }

    public function destroy(Course $course)
    {
        // SPEC §29 via `DeleteCourseAction`: a course with a roster, attempts,
        // progress, certificates or payment line items is archived rather than
        // removed. This used to call `$course->delete()` on a model with no
        // soft deletes, and `course_enrollments` / `payment_items` both cascade
        // — so it silently took the roster and its money with it.
        $result = app(DeleteCourseAction::class)->execute($course);

        $message = $result['soft']
            ? 'Course archived. It has '.$this->describe($result['blocked_by'])
                .', which stay on the record (SPEC §29).'
            : 'Course deleted.';

        return redirect()->route('admin.courses.index')->with('success', $message);
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function describe(array $counts): string
    {
        $labels = [
            'course_enrollments' => 'enrolment',
            'attendance_records' => 'attendance record',
            'activity_attempts' => 'activity attempt',
            'assessment_attempts' => 'assessment attempt',
            'student_lesson_progress' => 'progress record',
            'issued_certificates' => 'issued certificate',
            'payment_items' => 'payment record',
        ];

        $parts = [];
        foreach ($counts as $table => $count) {
            $label = $labels[$table] ?? $table;
            $parts[] = $count.' '.$label.($count === 1 ? '' : 's');
        }

        return implode(', ', $parts);
    }
}
