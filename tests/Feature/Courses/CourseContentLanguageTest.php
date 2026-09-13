<?php

use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\ResolveCourseContentLanguageAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * SPEC §7 "Supported Languages and Direction":
 *
 *   > Each course may have its own course language.
 *   >
 *   > **The platform UI language and course content language are separate
 *   > concepts.**
 *   >
 *   > Example: A user may use the UI in Dhivehi. The course may be Arabic.
 *
 * §15.3's block `language` setting existed and the player read it. But the
 * default is `auto`, and `auto` resolved to **nothing**:
 *
 *     const language = s.language && s.language !== 'auto' ? s.language : undefined;
 *
 * No `lang` attribute means the block inherits the page's, and the page's is
 * the **UI** language. So §7's own example produced Arabic text marked up as
 * Dhivehi — on every block an author had not tagged by hand, which is the
 * default state of every block.
 *
 * `courses.language` has been storable and settable since the table shipped
 * (the catalog screen offers EN/DV/AR/Mixed) and **nothing read it** but a
 * scope with no callers. The same taxonomy as §36's `submission_kind`: a
 * column the schema offers, the UI writes, and no reader consults.
 */
uses(RefreshDatabase::class);

function languageCourse(string $language): object
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);

    return app(SaveEngineCourseAction::class)->execute([
        'title' => 'Lang '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'language' => $language,
        'created_by' => $admin->id,
    ]);
}

function languageLesson(object $course): object
{
    $module = \App\Domains\Courses\Models\CourseModule::query()->create([
        'course_id' => $course->id,
        'title' => 'A module',
        'position' => 1,
        'status' => 'draft',
    ]);

    $lesson = app(SaveLessonAction::class)->execute([
        'course_id' => $course->id,
        'course_module_id' => $module->id,
        'title' => 'Reading',
    ]);
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'بسم الله'],
    ]);
    app(PublishLessonAction::class)->execute($lesson->fresh());

    return $lesson->fresh();
}

it('reads the course language nothing read before', function () {
    $resolve = app(ResolveCourseContentLanguageAction::class);

    expect($resolve->forCourse((int) languageCourse('ar')->id))->toBe('ar');
    expect($resolve->forCourse((int) languageCourse('dv')->id))->toBe('dv');
    expect($resolve->forCourse((int) languageCourse('en')->id))->toBe('en');
});

it('refuses to tag a mixed course with one language', function () {
    // A course that is deliberately more than one language has no single
    // honest answer, and guessing is worse than leaving the browser its
    // per-block heuristic.
    expect(app(ResolveCourseContentLanguageAction::class)
        ->forCourse((int) languageCourse('mixed')->id))->toBeNull();

    // `courses.language` is a plain string column with no enum behind it, so
    // anything can be stored. Only a language a browser understands is sent.
    expect(app(ResolveCourseContentLanguageAction::class)->tag('klingon'))->toBeNull();
    expect(app(ResolveCourseContentLanguageAction::class)->tag(null))->toBeNull();
});

it('sends the course language to the student player', function () {
    $course = languageCourse('ar');
    $lesson = languageLesson($course);
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);

    // The author preview and the student player are two controllers rendering
    // one page; both had the bug, so both are checked.
    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/player/'.$lesson->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Player/Show')
            ->where('courseLanguage', 'ar'));
});

it('sends null rather than a guess for a mixed course', function () {
    $lesson = languageLesson(languageCourse('mixed'));
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/player/'.$lesson->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('courseLanguage', null));
});

it('keeps the course language out of the frozen snapshot', function () {
    // Deliberate. Course language is course metadata — what language a lesson
    // is *in*, not what it *says* — so it is read live. Freezing it would also
    // need a backfill of every revision published before this slice.
    $lesson = languageLesson(languageCourse('ar'));
    $snapshot = app(\App\Domains\Courses\Actions\ResolvePublishedLessonAction::class)->execute($lesson->id);

    expect($snapshot)->not->toHaveKey('course_language');
    expect($snapshot['blocks'])->not->toBeEmpty();
});
