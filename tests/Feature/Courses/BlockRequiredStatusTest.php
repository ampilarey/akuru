<?php

use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\ResolvePublishedLessonAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Models\ContentBlock;
use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * SPEC §28.1 lists what a lesson revision snapshot must include:
 *
 *   > Lesson metadata required for rendering · Ordered block list · Block type
 *   > · Block data JSON · Block settings JSON · **Required/optional status** ·
 *   > Attached media references … · Revision number · Published timestamp ·
 *   > Published by
 *
 * Every item was there except required/optional status. The column existed on
 * `content_blocks` and `SaveContentBlockAction` honoured it — but no form ever
 * sent it, and `PublishLessonAction` did not copy it into the snapshot.
 *
 * Harmless only for as long as nothing could set it. §28.6 is the reason it is
 * not harmless once something can:
 *
 *   > Editing or reordering blocks must never change historical progress,
 *   > scores, attempt snapshots, or student-visible history.
 *
 * A flag absent from the snapshot is read live, so flipping it would change
 * what an already-published revision demands of students who are part-way
 * through it.
 */
uses(RefreshDatabase::class);

function requiredBlockLesson(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Required blocks '.uniqid(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Unit', 'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id, 'title' => 'Lesson', 'created_by' => $admin->id,
    ]);

    return compact('admin', 'course', 'module', 'lesson');
}

it('carries required status into the revision snapshot', function () {
    ['admin' => $admin, 'lesson' => $lesson] = requiredBlockLesson();
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id, 'type' => 'text',
        'data' => ['body' => 'Read this'], 'settings' => ['direction' => 'auto'],
        'is_required' => true,
    ]);
    app(PublishLessonAction::class)->execute($lesson, $admin->id);

    $snapshot = app(ResolvePublishedLessonAction::class)->execute($lesson->id);

    expect($snapshot['blocks'][0])->toHaveKey('is_required')
        ->and($snapshot['blocks'][0]['is_required'])->toBeTrue();
});

it('carries optional status too, rather than only marking the required ones', function () {
    ['admin' => $admin, 'lesson' => $lesson] = requiredBlockLesson();
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id, 'type' => 'text',
        'data' => ['body' => 'Extra reading'], 'settings' => ['direction' => 'auto'],
    ]);
    app(PublishLessonAction::class)->execute($lesson, $admin->id);

    expect(app(ResolvePublishedLessonAction::class)->execute($lesson->id)['blocks'][0]['is_required'])
        ->toBeFalse();
});

it('does not let a later edit change what a published revision requires', function () {
    // §28.6 in one test. Before this, the snapshot had no `is_required`, so a
    // reader had to consult the live block — and flipping the flag would move
    // the goalposts for a student part-way through the published revision.
    ['admin' => $admin, 'lesson' => $lesson] = requiredBlockLesson();
    $block = app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id, 'type' => 'text',
        'data' => ['body' => 'Read this'], 'settings' => ['direction' => 'auto'],
        'is_required' => true,
    ]);
    app(PublishLessonAction::class)->execute($lesson, $admin->id);
    $publishedRevisionId = $lesson->fresh()->current_revision_id;

    $block->update(['is_required' => false]);

    $frozen = app(ResolvePublishedLessonAction::class)->execute($lesson->id, $publishedRevisionId);

    expect($frozen['blocks'][0]['is_required'])->toBeTrue()
        ->and($block->fresh()->is_required)->toBeFalse();
});

it('records the new status in the next revision, not the old one', function () {
    ['admin' => $admin, 'lesson' => $lesson] = requiredBlockLesson();
    $block = app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id, 'type' => 'text',
        'data' => ['body' => 'Read this'], 'settings' => ['direction' => 'auto'],
        'is_required' => true,
    ]);
    app(PublishLessonAction::class)->execute($lesson, $admin->id);
    $first = $lesson->fresh()->current_revision_id;

    $block->update(['is_required' => false]);
    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);
    $second = $lesson->fresh()->current_revision_id;

    $resolve = app(ResolvePublishedLessonAction::class);

    expect($resolve->execute($lesson->id, $first)['blocks'][0]['is_required'])->toBeTrue()
        ->and($resolve->execute($lesson->id, $second)['blocks'][0]['is_required'])->toBeFalse();
});

it('saves the flag through the block route', function () {
    ['admin' => $admin, 'course' => $course, 'lesson' => $lesson] = requiredBlockLesson();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/blocks", [
            'lesson_id' => $lesson->id,
            'type' => 'text',
            'body' => 'Read this',
            'is_required' => true,
        ])
        ->assertRedirect();

    expect(ContentBlock::query()->where('lesson_id', $lesson->id)->value('is_required'))->toEqual(true);
});

it('defaults to optional when the route says nothing', function () {
    ['admin' => $admin, 'course' => $course, 'lesson' => $lesson] = requiredBlockLesson();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/blocks", [
            'lesson_id' => $lesson->id,
            'type' => 'text',
            'body' => 'Extra reading',
        ])
        ->assertRedirect();

    expect(ContentBlock::query()->where('lesson_id', $lesson->id)->value('is_required'))->toEqual(false);
});

it('keeps the flag on a media block, which builds its own payload', function () {
    // `StoreMediaContentBlockAction` does not pass the caller's array through;
    // it assembles a fresh one, so an unforwarded field silently becomes false.
    Storage::fake('local');
    Queue::fake();
    ['lesson' => $lesson] = requiredBlockLesson();

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $path = tempnam(sys_get_temp_dir(), 'png').'.png';
    file_put_contents($path, $png);

    app(\App\Domains\Courses\Actions\StoreMediaContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'image',
        'file' => new \Illuminate\Http\UploadedFile($path, 'shot.png', null, null, true),
        'is_required' => true,
    ]);

    expect(ContentBlock::query()->where('lesson_id', $lesson->id)->value('is_required'))->toEqual(true);
});

it('keeps the flag on an embedded video, the other media path', function () {
    ['lesson' => $lesson] = requiredBlockLesson();

    app(\App\Domains\Courses\Actions\StoreMediaContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'video',
        'embed_url' => 'https://www.youtube.com/watch?v=abc123',
        'is_required' => true,
    ]);

    expect(ContentBlock::query()->where('lesson_id', $lesson->id)->value('is_required'))->toEqual(true);
});

it('shows the flag on the outline screen', function () {
    ['admin' => $admin, 'course' => $course, 'lesson' => $lesson] = requiredBlockLesson();
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id, 'type' => 'text',
        'data' => ['body' => 'Read this'], 'settings' => ['direction' => 'auto'],
        'is_required' => true,
    ]);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get("/catalog/courses/{$course->id}/outline")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where(
            'modules.0.lessons.0.blocks.0.is_required',
            true,
        ));
});

it('keeps the flag when a block is duplicated', function () {
    ['lesson' => $lesson] = requiredBlockLesson();
    $block = app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id, 'type' => 'text',
        'data' => ['body' => 'Read this'], 'settings' => ['direction' => 'auto'],
        'is_required' => true,
    ]);

    $copy = app(\App\Domains\Courses\Actions\DuplicateContentBlockAction::class)->execute($block);

    expect((bool) $copy->is_required)->toBeTrue();
});
