<?php

namespace App\Domains\Website\Http\Controllers\Admin\PublicSite;

use App\Domains\Courses\Actions\DeleteCourseAction;
use App\Domains\Courses\Actions\ListDeletedCoursesAction;
use App\Domains\Courses\Actions\PublishCourseAction;
use App\Domains\Courses\Actions\RestoreCourseAction;
use App\Domains\Courses\Actions\SaveCourseLearningOutcomesAction;
use App\Domains\Courses\Actions\SaveCoursePublicCtaAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseCategory;
use App\Domains\HR\Actions\ListInstructorOptionsAction;
use App\Domains\Media\Actions\StorePublicMediaAction;
use App\Domains\Website\Actions\ForgetHomePageCacheAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use App\Support\Html\HtmlSanitizer;
use App\Support\Inertia\Phrases;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manage Courses (the website CMS). Inertia since C9 slice 11 (STATUS
 * §5jm), with its strings keyed for Dhivehi and Arabic. `role:super_admin`
 * on the route group. The body is authored HTML, sanitised here on every
 * write because `public/courses/show.blade.php` renders it raw.
 *
 * BACKLOG C16 slice N2 (STATUS §5nx), from the owner's walk of the live
 * form ("Slug * what is this?", "Cover Image URL *", "in the website it
 * doesnt show any course"): the address is filled from the title, the
 * cover is an upload, and a course is published to the website from the
 * list here rather than from the catalogue's workflow screen.
 */
class CourseController extends Controller
{
    private const RULES = [
        'course_category_id' => 'required|exists:course_categories,id',
        'title' => 'required|string|max:255',
        'slug' => 'nullable|string|max:255',
        'short_desc' => 'required|string',
        'body' => 'required|string',
        // The cover is an upload since N2; the stored path stays in
        // `cover_image`, which the public pages read as storage/<path>.
        'cover' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
        'language' => 'required|in:en,ar,dv,mixed',
        'level' => 'required|in:kids,youth,adult,all',
        'fee' => 'nullable|numeric|min:0',
        'status' => 'required|in:open,closed,upcoming',
        'seats' => 'nullable|integer|min:1',
        'whatsapp_number' => 'nullable|string|max:32',
        'syllabus_media_file_id' => 'nullable|integer|exists:media_files,id',
        // Who teaches it (C16 slice N6): shown on the course page, and the
        // courses a linked teacher may mark.
        'instructors' => 'nullable|array',
        'instructors.*' => 'integer|exists:instructors,id',
    ];

    public function index(): Response
    {
        $courses = Course::with('category')->orderBy('title')->paginate(15)->withQueryString();

        return Inertia::render('Website/Courses', [
            'courses' => collect($courses->items())->map(fn (Course $course) => $this->row($course))->values()->all(),
            'pagination' => ['current_page' => $courses->currentPage(), 'last_page' => $courses->lastPage(), 'prev' => $courses->previousPageUrl(), 'next' => $courses->nextPageUrl()],
            'total' => $courses->total(),
            // Publishing needs `courses.publish` (the engine's rule); the
            // button is offered only to a person who holds it.
            'can_publish' => (bool) request()->user()?->can('courses.publish'),
            'categories_count' => CourseCategory::query()->count(),
            't' => Phrases::once('admin'),
        ]);
    }

    /** "Every listing gets CSV export" (admin-panel audit, STATUS §5hs). */
    public function export(): StreamedResponse
    {
        $rows = Course::with('category')->orderBy('title')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['id', 'title', 'slug', 'category', 'status', 'updated_at']);
            foreach ($rows as $row) {
                Csv::put($out, [$row->id, $row->title, $row->slug, $row->category->name ?? '', $row->status, $row->updated_at?->toDateTimeString()]);
            }
            fclose($out);
        }, 'public-site-courses.csv', ['Content-Type' => 'text/csv']);
    }

    public function create(): Response
    {
        return Inertia::render('Website/CourseForm', ['course' => null] + $this->formProps());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->slugFor($validated, null);

        $course = Course::create($validated);
        $this->save($course, $request);

        return redirect()->route('admin.courses.index')->with('success', trans('admin.courses_flash_created'));
    }

    /**
     * Publish to the website (N2): `PublishCourseAction` walks the engine's
     * workflow to Published with the engine's own gate; the home page's
     * ten-minute cache is forgotten so the course shows at once.
     */
    public function publish(Request $request, Course $course): RedirectResponse
    {
        $canPublish = (bool) $request->user()?->can('courses.publish');
        if (! $canPublish) {
            return back()->withErrors(['workflow_status' => trans('admin.courses_publish_forbidden')]);
        }

        $result = app(PublishCourseAction::class)->execute((int) $course->id, $canPublish);
        if ($result['outcome'] === 'archived') {
            return back()->withErrors(['workflow_status' => trans('admin.courses_publish_archived')]);
        }
        if ($result['outcome'] === 'published') {
            app(ForgetHomePageCacheAction::class)->execute();
        }

        return back()->with('success', trans('admin.courses_flash_published', ['title' => $result['title']]));
    }

    public function edit(Course $course): Response
    {
        return Inertia::render('Website/CourseForm', ['course' => $this->full($course)] + $this->formProps());
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->slugFor($validated, $course);
        $course->update($validated);
        $this->save($course, $request);

        return redirect()->route('admin.courses.index')->with('success', trans('admin.courses_flash_updated'));
    }

    /**
     * The recovery list for #272's safe delete. Not the catalogue's Archive,
     * which is a workflow state on an ordinary visible row.
     */
    public function deleted(): Response
    {
        return Inertia::render('Courses/DeletedCourses', app(ListDeletedCoursesAction::class)->execute() + ['t' => Phrases::once('admin')]);
    }

    public function restore(int $course): RedirectResponse
    {
        // Route-model binding cannot reach this row: `Course` binds by slug and
        // the default query excludes soft-deleted records, so a deleted course
        // is a 404 to it. The id is explicit for that reason.
        $model = Course::onlyTrashed()->findOrFail($course);

        $result = app(RestoreCourseAction::class)->execute($model);

        $message = trans('admin.courses_flash_restored', ['title' => $result['title']]);
        if ($result['was_published']) {
            $message .= ' '.trans('admin.courses_flash_restored_draft');
        }

        return redirect()->route('admin.courses.deleted')->with('success', $message);
    }

    public function destroy(Course $course): RedirectResponse
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
            ? trans('admin.courses_flash_kept', ['holds' => $this->describe($result['blocked_by'])])
            : trans('admin.courses_flash_deleted');

        return redirect()->route('admin.courses.index')->with('success', $message);
    }

    /**
     * Validate and sanitise the body (PROFILE_CMS: the public site renders it raw).
     * The cover file is validated here and stored in `save()`; it is not a
     * column, so it leaves the array.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate(self::RULES);
        $validated['body'] = app(HtmlSanitizer::class)->clean($validated['body'], HtmlSanitizer::PROFILE_CMS);
        unset($validated['cover'], $validated['instructors']);

        return $validated;
    }

    /**
     * The course's address (N2). Typed, it is kept and must be free; blank, it
     * is the title's slug, made unique with a number. Scoped to live rows so
     * the "already in use" fires only when there is a course the admin can go
     * and look at; a deleted row holding the slug is refused with a message
     * that says so. A deleted course keeps its slug, deliberately: the slug
     * is the course's public address, and handing it to different content
     * would silently re-point every link and bookmark that already exists.
     *
     * @param  array<string, mixed>  $validated
     */
    private function slugFor(array $validated, ?Course $course): string
    {
        $typed = Str::slug((string) ($validated['slug'] ?? ''));
        $base = $typed !== '' ? $typed : (Str::slug((string) $validated['title']) ?: 'course');
        $live = fn (string $slug) => Course::query()->where('slug', $slug)->when($course, fn ($q) => $q->whereKeyNot($course->id))->exists();
        $deletedHolder = fn (string $slug) => Course::onlyTrashed()->where('slug', $slug)->when($course, fn ($q) => $q->whereKeyNot($course->id))->first();

        if ($typed !== '') {
            if ($live($typed)) {
                throw ValidationException::withMessages(['slug' => trans('admin.courses_slug_taken')]);
            }
            if ($deleted = $deletedHolder($typed)) {
                throw ValidationException::withMessages(['slug' => trans('admin.courses_slug_held', ['title' => $deleted->title])]);
            }

            return $typed;
        }

        $slug = $base;
        for ($n = 2; $live($slug) || $deletedHolder($slug) !== null; $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    /** The outcomes (one per line, EN/DV/AR), the public CTA, the cover upload and the instructors. */
    private function save(Course $course, Request $request): void
    {
        $course->instructors()->sync(array_values(array_map('intval', (array) $request->input('instructors', []))));
        if ($request->hasFile('cover')) {
            $stored = app(StorePublicMediaAction::class)->execute(
                $request->file('cover'),
                $request->user()?->id,
                ['image/jpeg', 'image/png', 'image/webp'],
                ['alt' => $course->title],
                'course-covers',
            );
            $course->forceFill(['cover_image' => $stored['path']])->save();
        }
        app(SaveCourseLearningOutcomesAction::class)->execute((int) $course->id, [
            'en' => $request->input('learning_outcomes_en', ''),
            'dv' => $request->input('learning_outcomes_dv', ''),
            'ar' => $request->input('learning_outcomes_ar', ''),
        ]);
        app(SaveCoursePublicCtaAction::class)->execute((int) $course->id, $request->input('whatsapp_number'), $request->input('syllabus_media_file_id'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'categories' => CourseCategory::ordered()->get()->map(fn (CourseCategory $category) => ['id' => $category->id, 'name' => $category->name])->values()->all(),
            'instructors' => app(ListInstructorOptionsAction::class)->execute(),
            't' => Phrases::once('admin'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Course $course): array
    {
        return [
            'id' => $course->id,
            'slug' => $course->slug,
            'title' => $course->title,
            'short_desc' => $course->short_desc ? Str::limit($course->short_desc, 60) : null,
            'category' => $course->category->name ?? null,
            'status' => $course->status,
            'workflow_status' => $course->workflow_status instanceof \BackedEnum ? (string) $course->workflow_status->value : (string) $course->workflow_status,
            // The public listing wants Published and Open or Upcoming
            // (`Course::scopeOpenForPublicListing`); the list says which is missing.
            'on_website' => $course->isPublished() && in_array($course->status, ['open', 'upcoming'], true),
            'updated_at' => $course->updated_at?->format('M d, Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function full(Course $course): array
    {
        $outcomes = is_array($course->learning_outcomes) ? $course->learning_outcomes : [];
        $lines = fn (string $locale) => implode("\n", is_array($outcomes[$locale] ?? null) ? $outcomes[$locale] : []);

        return [
            'id' => $course->id,
            'slug' => $course->slug,
            'title' => $course->title,
            'course_category_id' => $course->course_category_id,
            'short_desc' => $course->short_desc,
            'body' => $course->body,
            'cover_image' => $course->cover_image,
            'cover_url' => $course->cover_image ? asset('storage/'.$course->cover_image) : null,
            'language' => $course->language,
            'level' => $course->level,
            'status' => $course->status,
            'fee' => $course->fee,
            'seats' => $course->seats,
            'whatsapp_number' => $course->whatsapp_number,
            'syllabus_media_file_id' => $course->syllabus_media_file_id,
            'learning_outcomes_en' => $lines('en'),
            'learning_outcomes_dv' => $lines('dv'),
            'learning_outcomes_ar' => $lines('ar'),
            'instructor_ids' => $course->instructors()->pluck('instructors.id')->map(fn ($id): int => (int) $id)->values()->all(),
        ];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function describe(array $counts): string
    {
        $parts = [];
        foreach ($counts as $table => $count) {
            $parts[] = trans()->has('admin.courses_hold_'.$table)
                ? trans_choice('admin.courses_hold_'.$table, $count, ['count' => $count])
                : $count.' '.$table;
        }

        return implode(', ', $parts);
    }
}
