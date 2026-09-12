<?php

use App\Domains\Courses\Actions\DeleteCourseAction;
use App\Domains\Courses\Actions\ListDeletedCoursesAction;
use App\Domains\Courses\Actions\RestoreCourseAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The recovery half of SPEC §29's safe delete.
 *
 * #272 made Delete safe: a course with a roster, attempts or payment line items
 * is soft-deleted rather than cascaded away, because
 * `course_enrollments.course_id` and `payment_items.course_id` both CASCADE and
 * rule 12 says ledgers are append-only. It did not make Delete **reversible**.
 * Nothing in the app called `withTrashed()`, `onlyTrashed()` or `restore()`.
 *
 * Two consequences, both pinned below:
 *
 *   1. A deleted course left every screen and nobody could bring it back or
 *      even see that it existed — a safe delete that is, in practice, final.
 *   2. `unique:courses` reads the raw table, so the deleted row still held its
 *      slug. The admin was told only "The slug has already been taken", with no
 *      course on any screen holding it — an unanswerable error.
 *
 * Note this is distinct from the catalogue's Archive
 * (`workflow_status = 'archived'`), which is a status on an ordinary visible
 * row, set and cleared from the Catalog screen.
 */
uses(RefreshDatabase::class);

function recoverableCourse(string $title = 'Recoverable', string $workflow = 'published'): Course
{
    // Built here rather than reused from DeleteCourseTest: a helper declared in
    // one Pest file is not visible in another.
    $course = Course::query()->create([
        'course_category_id' => DB::table('course_categories')->insertGetId([
            'name' => 'Delete recovery', 'slug' => 'delete-recovery-'.Str::random(6), 'order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'title' => $title,
        'slug' => Str::slug($title).'-'.Str::random(6),
        'short_desc' => 'For the delete-recovery test.',
        'body' => 'Body.',
        'cover_image' => '',
        'workflow_status' => $workflow,
        'course_type' => 'general',
        'status' => 'open',
    ]);

    return $course->fresh();
}

function courseWithRoster(string $title = 'Has a roster', string $workflow = 'published'): Course
{
    $course = recoverableCourse($title, $workflow);
    CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => makeRegistrationStudent()->id,
        'status' => 'active',
        'payment_status' => 'confirmed',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => 0,
    ]);

    return $course;
}

it('lists a deleted course with the records that kept it alive', function () {
    $course = courseWithRoster('Withdrawn intake');
    app(DeleteCourseAction::class)->execute($course);

    $payload = app(ListDeletedCoursesAction::class)->execute();

    expect($payload['courses'])->toHaveCount(1)
        ->and($payload['courses'][0]['title'])->toBe('Withdrawn intake')
        ->and($payload['courses'][0]['deleted_at'])->not->toBeNull()
        // The holds are the justification for the row still existing. A screen
        // that says "deleted" without saying why leaves an admin guessing
        // whether anything was lost.
        ->and($payload['courses'][0]['holds'])->toHaveKey('course_enrollments')
        ->and($payload['courses'][0]['enrolled'])->toBe(1);
});

it('brings a deleted course back with its roster intact', function () {
    $course = courseWithRoster('Restore me');
    app(DeleteCourseAction::class)->execute($course);
    expect(Course::query()->whereKey($course->id)->exists())->toBeFalse();

    $result = app(RestoreCourseAction::class)->execute(Course::onlyTrashed()->findOrFail($course->id));

    expect($result['title'])->toBe('Restore me')
        ->and(Course::query()->whereKey($course->id)->exists())->toBeTrue()
        // §29's whole point: the enrolment was never touched, so it is simply
        // still attached.
        ->and(CourseEnrollment::query()->where('course_id', $course->id)->count())->toBe(1);
});

it('restores a published course as a draft rather than back onto the public site', function () {
    $course = courseWithRoster('Was published', 'published');
    app(DeleteCourseAction::class)->execute($course);

    $result = app(RestoreCourseAction::class)->execute(Course::onlyTrashed()->findOrFail($course->id));

    // Deleting is usually a withdrawal. A restore that re-listed the course
    // publicly would publish content to the website as a side effect of an
    // admin clicking "Restore".
    expect($result['was_published'])->toBeTrue()
        ->and(Course::query()->findOrFail($course->id)->workflow_status->value)->toBe('draft');
});

it('leaves a course that was already a draft as a draft', function () {
    $course = courseWithRoster('Never published', 'draft');
    app(DeleteCourseAction::class)->execute($course);

    $result = app(RestoreCourseAction::class)->execute(Course::onlyTrashed()->findOrFail($course->id));

    expect($result['was_published'])->toBeFalse()
        ->and(Course::query()->findOrFail($course->id)->workflow_status->value)->toBe('draft');
});

it('refuses to restore a course that was never deleted', function () {
    $course = recoverableCourse('Still here');

    expect(fn () => app(RestoreCourseAction::class)->execute($course))
        ->toThrow(ValidationException::class);
});

it('names the deleted course holding a slug instead of saying it is taken', function () {
    $course = courseWithRoster('Quran Foundations');
    $slug = $course->slug;
    app(DeleteCourseAction::class)->execute($course);

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $response = $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.courses.store'), [
            'course_category_id' => $course->course_category_id,
            'title' => 'A new course at the same address',
            'slug' => $slug,
            'short_desc' => 'x',
            'body' => 'y',
            'cover_image' => 'cover.jpg',
            'language' => 'en',
            'level' => 'adult',
            'status' => 'open',
        ]);

    $response->assertSessionHasErrors('slug');
    $error = session('errors')->first('slug');

    expect($error)->toContain('Quran Foundations')
        ->and($error)->toContain('Deleted courses');

    // And the slug is genuinely still held. That is deliberate: it is the
    // course's public address, and handing it to different content would
    // silently re-point every link and bookmark that already exists.
    expect(Course::query()->where('slug', $slug)->exists())->toBeFalse()
        ->and(Course::withTrashed()->where('slug', $slug)->count())->toBe(1);
});

it('still refuses a slug a live course is using', function () {
    $live = recoverableCourse('Live course');

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    // The scoped unique rule must not have opened a hole in the ordinary case.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.courses.store'), [
            'course_category_id' => $live->course_category_id,
            'title' => 'Clashing course',
            'slug' => $live->slug,
            'short_desc' => 'x',
            'body' => 'y',
            'cover_image' => 'cover.jpg',
            'language' => 'en',
            'level' => 'adult',
            'status' => 'open',
        ])->assertSessionHasErrors('slug');
});

it('keeps the deleted list to admins', function () {
    $plain = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($plain)
        ->get(route('admin.courses.deleted'))->assertForbidden();
});
