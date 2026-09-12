<?php

use App\Domains\Courses\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Unpublished courses were on the public website.
 *
 * Found by the browser walk for the delete-recovery slice, which asserted that
 * restoring a course does not put it back on the public site. It failed — and
 * not because the restore was wrong. The restore correctly set the course back
 * to `draft`, and the public page served it anyway.
 *
 * Two separate holes:
 *
 *   - `scopeOpenForPublicListing()` filtered on `status` (open/upcoming, which
 *     is about *enrolment*) and never on `workflow_status` (which is about
 *     *publication*). In the seeded dataset 11 of 12 courses were drafts, and
 *     all 11 were listed publicly.
 *   - `CourseController::show()` had no gate whatsoever. Any course was
 *     readable at its own URL, in any state, by anybody who knew the slug.
 *
 * A course in `draft` or `in_review` is work in progress — unreviewed copy,
 * provisional pricing, a syllabus still being argued about. None of it was ever
 * meant to be the public face of the institute.
 */
uses(RefreshDatabase::class);

function courseInState(string $workflow, string $status = 'open'): Course
{
    return Course::query()->create([
        'course_category_id' => DB::table('course_categories')->insertGetId([
            'name' => 'Publication', 'slug' => 'publication-'.Str::random(6), 'order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'title' => ucfirst($workflow).' course',
        'slug' => $workflow.'-course-'.Str::random(6),
        'short_desc' => 'Short.',
        'body' => 'Body.',
        'cover_image' => '',
        'workflow_status' => $workflow,
        'course_type' => 'general',
        'status' => $status,
    ]);
}

it('lists only published courses on the public site', function () {
    courseInState('draft');
    courseInState('in_review');
    $published = courseInState('published');

    $listed = Course::openForPublicListing()->pluck('id');

    // `status = open` is not permission to publish. It says enrolment is open,
    // which is a different question entirely.
    expect($listed->all())->toBe([$published->id]);
});

it('does not serve a draft course at its own url', function () {
    $draft = courseInState('draft');

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $draft))
        ->assertNotFound();
});

it('does not serve an in-review course at its own url', function () {
    $review = courseInState('in_review');

    // In review is the state where a dean is still arguing with the copy.
    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $review))
        ->assertNotFound();
});

it('still serves a published course', function () {
    $published = courseInState('published');

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $published))
        ->assertOk();
});

it('stops serving a course that is unpublished after the fact', function () {
    $course = courseInState('published');

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $course))->assertOk();

    // The case the delete-recovery walk actually hit: a course that was
    // published, then put back to draft, must leave the public site.
    $course->forceFill(['workflow_status' => 'draft'])->save();

    $this->withoutLocalizationMiddleware()
        ->get(route('public.courses.show', $course))->assertNotFound();
});
