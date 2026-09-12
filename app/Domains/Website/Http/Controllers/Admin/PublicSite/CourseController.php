<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\Courses\Actions\DeleteCourseAction;
use App\Domains\Courses\Actions\ListDeletedCoursesAction;
use App\Domains\Courses\Actions\RestoreCourseAction;
use App\Domains\Courses\Actions\SaveCourseLearningOutcomesAction;
use App\Domains\Courses\Actions\SaveCoursePublicCtaAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            // Scoped to live rows so the generic "already been taken" fires
            // only when there is a course the admin can actually go and look
            // at. A deleted row holding the slug is handled below, with a
            // message that says so.
            'slug' => ['required', 'string', 'max:255', Rule::unique('courses', 'slug')->whereNull('deleted_at')],
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

        // A deleted course keeps its slug, deliberately: the slug is the
        // course's public address, and handing it to different content would
        // silently re-point every link and bookmark that already exists. A 404
        // is recoverable; serving unrelated content under a known URL is not.
        // So the answer is to refuse, and to say which course is holding it.
        if ($deleted = Course::onlyTrashed()->where('slug', $validated['slug'])->first()) {
            return back()->withInput()->withErrors([
                'slug' => 'The deleted course "'.$deleted->title.'" still uses this address. '
                    .'Restore it from Deleted courses, or choose a different slug.',
            ]);
        }

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

    /**
     * The recovery list for #272's safe delete. Not the catalogue's Archive,
     * which is a workflow state on an ordinary visible row.
     */
    public function deleted()
    {
        return \Inertia\Inertia::render(
            'Courses/DeletedCourses',
            app(ListDeletedCoursesAction::class)->execute(),
        );
    }

    public function restore(int $course)
    {
        // Route-model binding cannot reach this row: `Course` binds by slug and
        // the default query excludes soft-deleted records, so a deleted course
        // is a 404 to it. The id is explicit for that reason.
        $model = Course::onlyTrashed()->findOrFail($course);

        $result = app(RestoreCourseAction::class)->execute($model);

        $message = 'Restored "'.$result['title'].'".';
        if ($result['was_published']) {
            $message .= ' It is back as a draft — publish it again when you are ready,'
                .' so restoring does not put it back on the public site by itself.';
        }

        return redirect()->route('admin.courses.deleted')->with('success', $message);
    }

    public function destroy(Course $course)
    {
        // SPEC §29 via `DeleteCourseAction`: a course with a roster, attempts,
        // progress, certificates or payment line items is kept rather than
        // removed. This used to call `$course->delete()` on a model with no
        // soft deletes, and `course_enrollments` / `payment_items` both cascade
        // — so it silently took the roster and its money with it.
        $result = app(DeleteCourseAction::class)->execute($course);

        // Deliberately not the word "archived": the catalogue already uses that
        // for `workflow_status`, which is a different thing done from a
        // different screen and reversed a different way.
        $message = $result['soft']
            ? 'Course removed from the catalogue. It has '.$this->describe($result['blocked_by'])
                .', which stay on the record (SPEC §29) — you can restore it from Deleted courses.'
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
