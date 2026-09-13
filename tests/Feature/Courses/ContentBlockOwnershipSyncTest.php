<?php

use App\Domains\Courses\Actions\DeleteCourseModuleAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\SyncContentBlockOwnershipAction;
use App\Domains\Courses\Models\CourseModule;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Courses\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §14 "Content Block Ownership" states a guarantee, not a preference:
 *
 *   > Content blocks belong to lessons. `lesson_id` is the source of truth.
 *   >
 *   > `course_id` and `module_id` on `content_blocks` are denormalized for
 *   > query performance only.
 *   >
 *   > Whenever a lesson is moved to another module or course, the related
 *   > `content_blocks.course_id` and `content_blocks.module_id` must be
 *   > **synced automatically**. Use a model observer or service method to
 *   > guarantee this.
 *   >
 *   > **No code may rely on `content_blocks.course_id` or
 *   > `content_blocks.module_id` unless this sync guarantee exists.**
 *
 * It did not exist. Blocks took both ids from the lesson at creation and never
 * looked again, so moving a lesson between modules left every block pointing
 * at the module it used to be in. Verified by probe before fixing: a lesson
 * moved from module 1 to module 2 left its block on module 1.
 *
 * And code **did** rely on it, which is what §14's last sentence forbids.
 * `DeleteCourseModuleAction` implements §12's "Delete draft modules if safe"
 * by counting dependents, and `content_blocks.course_module_id` is one of the
 * three tables counted. Stale ids make that check wrong in both directions,
 * and the second direction is the dangerous one — see the last two tests.
 */
uses(RefreshDatabase::class);

function ownershipFixture(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Ownership '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    $from = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Module A', 'created_by' => $admin->id,
    ]);
    $to = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Module B', 'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $from->id, 'title' => 'Lesson', 'created_by' => $admin->id,
    ]);
    $block = app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id, 'type' => 'text', 'data' => ['body' => 'Body'], 'created_by' => $admin->id,
    ]);

    return compact('admin', 'course', 'from', 'to', 'lesson', 'block');
}

function moveLesson(Lesson $lesson, CourseModule $module): Lesson
{
    return app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => $lesson->title,
        'slug' => $lesson->slug,
        'position' => $lesson->position,
    ], $lesson);
}

it('moves a lesson\'s blocks with it', function () {
    // The defect, stated plainly. Before this slice the block stayed behind.
    ['lesson' => $lesson, 'block' => $block, 'to' => $to] = ownershipFixture();

    moveLesson($lesson, $to);

    expect((int) $block->refresh()->course_module_id)->toBe((int) $to->id);
});

it('guarantees it through an observer, not through one Action remembering', function () {
    // §14 asks for a *guarantee*. A call inside SaveLessonAction would only be
    // as good as every future caller remembering it, so the sync hangs off the
    // model — a direct save has to work too.
    ['lesson' => $lesson, 'block' => $block, 'to' => $to] = ownershipFixture();

    $lesson->course_module_id = $to->id;
    $lesson->save();

    expect((int) $block->refresh()->course_module_id)->toBe((int) $to->id);
});

it('follows a lesson to a different course as well as a different module', function () {
    ['admin' => $admin, 'lesson' => $lesson, 'block' => $block] = ownershipFixture();
    $other = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Elsewhere '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    $elsewhere = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $other->id, 'title' => 'Far module', 'created_by' => $admin->id,
    ]);

    moveLesson($lesson, $elsewhere);

    expect((int) $block->refresh()->course_id)->toBe((int) $other->id)
        ->and((int) $block->refresh()->course_module_id)->toBe((int) $elsewhere->id);
});

it('leaves blocks alone when the lesson only gets renamed', function () {
    // The observer fires on a move, not on every save — an ordinary title edit
    // must not rewrite every block in the lesson.
    ['lesson' => $lesson, 'block' => $block, 'from' => $from] = ownershipFixture();
    $before = $block->refresh()->updated_at;

    app(SaveLessonAction::class)->execute([
        'course_module_id' => $from->id,
        'title' => 'Renamed',
        'slug' => $lesson->slug,
    ], $lesson);

    expect($block->refresh()->updated_at->eq($before))->toBeTrue();
});

it('reports nothing stale once the guarantee holds', function () {
    // The question §14 exists to keep answerable as "none", and the one a
    // verification script would ask.
    ['lesson' => $lesson, 'to' => $to] = ownershipFixture();
    moveLesson($lesson, $to);

    expect(app(SyncContentBlockOwnershipAction::class)->staleCount())->toBe(0);
});

it('repairs rows that drifted before the guarantee existed', function () {
    // What the migration does. Written straight to the database to reproduce
    // the state a deployment could already be in.
    //
    // The drift is a *valid but wrong* module id, not a dangling one: a
    // foreign key already stops a block pointing at a module that does not
    // exist. What nothing stopped was a block pointing at a real module the
    // lesson had since left — which is exactly what a move produced.
    ['lesson' => $lesson, 'block' => $block, 'from' => $from, 'to' => $to] = ownershipFixture();
    DB::table('content_blocks')->where('id', $block->id)->update(['course_module_id' => $to->id]);

    expect(app(SyncContentBlockOwnershipAction::class)->staleCount())->toBe(1);

    app(SyncContentBlockOwnershipAction::class)->execute($lesson->refresh());

    expect(app(SyncContentBlockOwnershipAction::class)->staleCount())->toBe(0)
        ->and((int) $block->refresh()->course_module_id)->toBe((int) $from->id);
});

it('is idempotent, which is what makes it safe on every save', function () {
    ['lesson' => $lesson] = ownershipFixture();
    $sync = app(SyncContentBlockOwnershipAction::class);

    expect($sync->execute($lesson))->toBe(0)
        ->and($sync->execute($lesson))->toBe(0);
});

it('stops a vacated module looking permanently undeletable', function () {
    // §12: "Delete draft modules if safe". With stale ids the module a lesson
    // moved *out* of still counted that lesson's blocks, so it could never be
    // deleted — the blocks it was refusing on were not its own any more.
    ['lesson' => $lesson, 'from' => $from, 'to' => $to] = ownershipFixture();

    moveLesson($lesson, $to);

    app(DeleteCourseModuleAction::class)->execute($from->refresh());

    expect(CourseModule::query()->find($from->id))->toBeNull();
});

it('stops a module being deleted out from under blocks that moved in', function () {
    // The dangerous direction. Blocks that moved *in* were not counted, so the
    // safety check would allow a delete the database then refuses with a
    // RESTRICT violation — which reaches the admin as a 500 and an SQL string.
    ['lesson' => $lesson, 'to' => $to] = ownershipFixture();

    moveLesson($lesson, $to);

    expect(fn () => app(DeleteCourseModuleAction::class)->execute($to->refresh()))
        ->toThrow(ValidationException::class);

    expect(CourseModule::query()->find($to->id))->not->toBeNull();
});

it('keeps lesson_id as the source of truth, never the other way round', function () {
    // §14: "`lesson_id` is the source of truth ... denormalized for query
    // performance only." Writing a block's module id has no effect on where
    // the block actually lives — the lesson wins, every time.
    ['lesson' => $lesson, 'block' => $block, 'from' => $from, 'to' => $to] = ownershipFixture();
    DB::table('content_blocks')->where('id', $block->id)->update(['course_module_id' => $to->id]);

    app(SyncContentBlockOwnershipAction::class)->execute($lesson);

    expect((int) $block->refresh()->course_module_id)->toBe((int) $from->id)
        ->and((int) $block->refresh()->lesson_id)->toBe((int) $lesson->id);
});

it('registers the observer so the guarantee cannot be quietly unhooked', function () {
    ['lesson' => $lesson] = ownershipFixture();

    expect(Lesson::getEventDispatcher()->hasListeners('eloquent.saved: '.Lesson::class))->toBeTrue()
        ->and($lesson)->toBeInstanceOf(Lesson::class);
});

it('carries §14\'s full field list on content_blocks', function () {
    foreach ([
        'id', 'course_id', 'course_module_id', 'lesson_id', 'type', 'position',
        'title', 'data', 'settings', 'is_required', 'created_by', 'created_at', 'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('content_blocks', $column))
            ->toBeTrue("content_blocks.{$column} is missing");
    }
});
