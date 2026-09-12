<?php

use App\Domains\Courses\Actions\ResolveOfferingTaxonomyAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Offerings\Actions\SaveCourseOfferingAction;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * SPEC §10.5 and §10.6 say where audience and level live, in as many words:
 *
 *   > Audience is stored on **`course_offerings`** (§11), so the same course
 *   > template can run for different audiences without duplicating content.
 *
 *   > **Offerings:** `level_id` on `course_offerings` combines with
 *   > `audience_id` (§10.5) to describe *who* and *how advanced* a batch is —
 *   > e.g. Nahw Level 1 for Kids vs Nahw Level 2 for Adults on the same course
 *   > template.
 *
 * Neither column existed, and both taxonomies were built anyway. `audiences`
 * and `course_levels` are real admin-managed trilingual tables, seeded with
 * exactly §10.5's and §10.6's example values, with List/Save Actions, admin
 * screens, CSV export and a nav link.
 *
 * They had nowhere to attach. `level_id` is referenced by exactly one model in
 * the whole codebase (`GlossaryItem`), and **`audience_id` by nothing at all**
 * — an entire admin-managed dimension with zero referencing rows.
 *
 * This is the storable-but-unsettable pattern inverted: not a field with no
 * control, but a **control with no field**. An admin could add, rename,
 * reorder, deactivate and export audiences all day, and nothing in the product
 * could ever be one.
 */
uses(RefreshDatabase::class);

function taxonomyCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Nahw '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);

    return ['admin' => $admin, 'course' => $course];
}

function audienceId(string $slug): int
{
    return (int) DB::table('audiences')->where('slug', $slug)->value('id');
}

function levelId(string $slug): int
{
    return (int) DB::table('course_levels')->where('slug', $slug)->value('id');
}

it('stores audience and level where §10.5 and §10.6 say they live', function () {
    expect(Schema::hasColumn('course_offerings', 'audience_id'))->toBeTrue()
        ->and(Schema::hasColumn('course_offerings', 'level_id'))->toBeTrue();
});

it('runs one course template as two different batches', function () {
    // §10.6's own example, which was unexpressible: "Nahw Level 1 for Kids vs
    // Nahw Level 2 for Adults on the same course template".
    ['course' => $course] = taxonomyCourse();

    $kids = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Nahw for kids '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
        'audience_id' => audienceId('kids'),
        'level_id' => levelId('level-1'),
    ]);
    $adults = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Nahw for adults '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
        'audience_id' => audienceId('adults'),
        'level_id' => levelId('level-2'),
    ]);

    expect($kids->course_id)->toBe($adults->course_id)
        ->and($kids->audience_id)->not->toBe($adults->audience_id)
        ->and($kids->level_id)->not->toBe($adults->level_id);
});

it('carries the choice through the offering form', function () {
    // The half that decides whether any of this is reachable. The §39 slice
    // was a reminder that a column the validator does not list is dropped on
    // the way in however it is posted.
    ['admin' => $admin, 'course' => $course] = taxonomyCourse();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/offerings', [
            'course_id' => $course->id,
            'title' => 'Posted batch '.uniqueFixtureSuffix(),
            'delivery_mode' => 'live_online',
            'audience_id' => audienceId('school-children'),
            'level_id' => levelId('beginner'),
        ])
        ->assertRedirect();

    $offering = CourseOffering::query()->latest('id')->first();

    expect((int) $offering->audience_id)->toBe(audienceId('school-children'))
        ->and((int) $offering->level_id)->toBe(levelId('beginner'));
});

it('shows them on the offerings screen with their names, not their ids', function () {
    ['admin' => $admin, 'course' => $course] = taxonomyCourse();
    app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Shown batch '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
        'audience_id' => audienceId('kids'),
        'level_id' => levelId('foundation'),
    ]);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/offerings')
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.audience', 'Kids')
            ->where('rows.0.level', 'Foundation')
            ->has('audiences')
            ->has('levels')
        );
});

it('puts them in the CSV an admin actually reads', function () {
    ['admin' => $admin, 'course' => $course] = taxonomyCourse();
    app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Exported batch '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
        'audience_id' => audienceId('adults'),
        'level_id' => levelId('advanced'),
    ]);

    $csv = $this->actingAs($admin)->withoutLocalizationMiddleware()
        ->get('/catalog/offerings/export')->streamedContent();

    expect($csv)->toContain('audience')
        ->and($csv)->toContain('Adults')
        ->and($csv)->toContain('Advanced');
});

it('leaves an existing choice alone when the caller does not mention it', function () {
    ['course' => $course] = taxonomyCourse();
    $offering = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Keep '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
        'audience_id' => audienceId('kids'),
    ]);

    app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Renamed',
        'delivery_mode' => 'face_to_face',
    ], $offering);

    expect((int) $offering->refresh()->audience_id)->toBe(audienceId('kids'));
});

it('clears the choice when the form posts it back empty', function () {
    ['course' => $course] = taxonomyCourse();
    $offering = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Clearable '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
        'audience_id' => audienceId('kids'),
    ]);

    app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => $offering->title,
        'delivery_mode' => 'face_to_face',
        'audience_id' => '',
    ], $offering);

    expect($offering->refresh()->audience_id)->toBeNull();
});

it('refuses an audience that is not on offer', function () {
    ['course' => $course] = taxonomyCourse();

    expect(fn () => app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Bad '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
        'audience_id' => 99999,
    ]))->toThrow(ValidationException::class);
});

it('stops offering a deactivated audience without blanking the batches using it', function () {
    // Admin-managed means an audience can be retired. A batch that already
    // ran for "School children" must keep saying so.
    ['course' => $course] = taxonomyCourse();
    $offering = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Historic '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
        'audience_id' => audienceId('school-children'),
    ]);

    DB::table('audiences')->where('slug', 'school-children')->update(['active' => false]);

    $taxonomy = app(ResolveOfferingTaxonomyAction::class);

    expect(collect($taxonomy->options()['audiences'])->pluck('label'))->not->toContain('School children')
        ->and($taxonomy->audienceExists(audienceId('school-children')))->toBeFalse()
        ->and((int) $offering->refresh()->audience_id)->toBe(audienceId('school-children'));
});

it('reads the taxonomy through an Action, never through a Courses model', function () {
    // Rule 3, and `Phase1ABoundariesTest` fails outright if any file under
    // app/Domains/Offerings so much as names a Courses model. So there is no
    // Eloquent relation here — the ids are plain and
    // ResolveOfferingTaxonomyAction is the seam, the same one the §38
    // payment-method slice arrived at.
    $root = base_path('app/Domains/Offerings');
    $offenders = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        if (preg_match('/Courses\\\\Models\\\\(Audience|CourseLevel)/', (string) file_get_contents($file->getPathname()))) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBeEmpty();
});

it('leaves courses.level alone, deprecated but untouched (rule 9)', function () {
    // `courses.level` is an enum('kids','youth','adult','all') — audience
    // values wearing the name "level", hardcoded, and on the course rather
    // than the offering. All three are wrong per §10.2/§10.6, and none is
    // fixed here: the column is populated and the public site filters on it
    // live, so it cannot be dropped in the deploy that stops depending on it.
    expect(Schema::hasColumn('courses', 'level'))->toBeTrue();
});
