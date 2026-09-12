<?php

use App\Domains\Courses\Actions\DuplicateContentBlockAction;
use App\Domains\Courses\Actions\ReorderContentBlocksAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Models\ContentBlock;
use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §16 asks the content block builder to support "Reordering blocks",
 * "Duplicating blocks where safe", and says "Reordering must persist
 * correctly."
 *
 * The reorder route and action existed; nothing in the UI ever called them.
 * The action itself accepted any list of ids and renumbered by array index,
 * so a partial or foreign list left the lesson with colliding positions and
 * reported success. Duplicating a block was not built at all.
 */
uses(RefreshDatabase::class);

function outlineFixture(string $title = 'Builder'): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => $title,
        'subject_id' => CourseSubject::query()->where('slug', 'arabic')->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Unit 1',
        'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Lesson 1',
        'created_by' => $admin->id,
    ]);

    return compact('admin', 'course', 'lesson');
}

function makeBlock(int $lessonId, string $body): ContentBlock
{
    return app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lessonId,
        'type' => 'text',
        'data' => ['body' => $body],
        'settings' => ['direction' => 'auto'],
    ]);
}

it('persists a new block order', function () {
    ['lesson' => $lesson] = outlineFixture();
    $a = makeBlock($lesson->id, 'A');
    $b = makeBlock($lesson->id, 'B');
    $c = makeBlock($lesson->id, 'C');

    app(ReorderContentBlocksAction::class)->execute($lesson->id, [$c->id, $a->id, $b->id]);

    expect($lesson->fresh()->blocks->pluck('data.body')->all())->toBe(['C', 'A', 'B']);
});

it('refuses a partial list instead of leaving two blocks on one position', function () {
    // The old action wrote `position = array index` for the ids it was handed
    // and left the rest alone: [A,B,C] reordered as [C,B] put C and B on 0 and
    // 1 alongside A and B — the order shown was then a database tie-break.
    ['lesson' => $lesson] = outlineFixture();
    $a = makeBlock($lesson->id, 'A');
    $b = makeBlock($lesson->id, 'B');
    makeBlock($lesson->id, 'C');

    expect(fn () => app(ReorderContentBlocksAction::class)->execute($lesson->id, [$b->id, $a->id]))
        ->toThrow(ValidationException::class);

    expect($lesson->fresh()->blocks->pluck('data.body')->all())->toBe(['A', 'B', 'C']);
});

it('refuses a list naming a block from another lesson', function () {
    ['lesson' => $lesson] = outlineFixture();
    ['lesson' => $other] = outlineFixture('Elsewhere');
    $a = makeBlock($lesson->id, 'A');
    $b = makeBlock($lesson->id, 'B');
    $foreign = makeBlock($other->id, 'Not mine');

    expect(fn () => app(ReorderContentBlocksAction::class)->execute($lesson->id, [$a->id, $foreign->id]))
        ->toThrow(ValidationException::class);

    expect($lesson->fresh()->blocks->pluck('id')->all())->toBe([$a->id, $b->id])
        ->and($foreign->fresh()->lesson_id)->toBe($other->id);
});

it('refuses a list that repeats a block', function () {
    ['lesson' => $lesson] = outlineFixture();
    $a = makeBlock($lesson->id, 'A');
    makeBlock($lesson->id, 'B');

    expect(fn () => app(ReorderContentBlocksAction::class)->execute($lesson->id, [$a->id, $a->id]))
        ->toThrow(ValidationException::class);
});

it('reorders through the route', function () {
    ['admin' => $admin, 'course' => $course, 'lesson' => $lesson] = outlineFixture();
    $a = makeBlock($lesson->id, 'A');
    $b = makeBlock($lesson->id, 'B');

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/blocks/reorder", [
            'lesson_id' => $lesson->id,
            'block_ids' => [$b->id, $a->id],
        ])
        ->assertRedirect();

    expect($lesson->fresh()->blocks->pluck('data.body')->all())->toBe(['B', 'A']);
});

it('will not reorder a lesson belonging to a different course', function () {
    // The route only checked that the lesson existed. A reorder posted under
    // course A could renumber course B's lesson, and the redirect sent the
    // author back to A showing nothing changed.
    ['admin' => $admin, 'course' => $course] = outlineFixture();
    ['lesson' => $other] = outlineFixture('Elsewhere');
    $x = makeBlock($other->id, 'X');
    $y = makeBlock($other->id, 'Y');

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/blocks/reorder", [
            'lesson_id' => $other->id,
            'block_ids' => [$y->id, $x->id],
        ])
        ->assertNotFound();

    expect($other->fresh()->blocks->pluck('data.body')->all())->toBe(['X', 'Y']);
});

it('duplicates a block directly after the original', function () {
    // Not at the end: duplicating is how an author builds a run of similar
    // blocks, and landing at the bottom would mean a reorder every time.
    ['lesson' => $lesson] = outlineFixture();
    $a = makeBlock($lesson->id, 'A');
    makeBlock($lesson->id, 'B');
    makeBlock($lesson->id, 'C');

    $copy = app(DuplicateContentBlockAction::class)->execute($a);

    expect($lesson->fresh()->blocks->pluck('data.body')->all())->toBe(['A', 'A', 'B', 'C'])
        ->and($copy->id)->not->toBe($a->id)
        ->and($lesson->fresh()->blocks->pluck('position')->all())->toBe([0, 1, 2, 3]);
});

it('copies the block content rather than sharing it', function () {
    ['lesson' => $lesson] = outlineFixture();
    $block = app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'instruction',
        'title' => 'Read first',
        'data' => ['body' => 'Listen closely', 'tone' => 'tip'],
        'settings' => ['direction' => 'rtl'],
    ]);

    $copy = app(DuplicateContentBlockAction::class)->execute($block);

    expect($copy->type)->toBe('instruction')
        ->and($copy->title)->toBe('Read first')
        ->and($copy->data)->toBe(['body' => 'Listen closely', 'tone' => 'tip'])
        ->and($copy->settings)->toBe(['direction' => 'rtl']);

    // Editing the copy must not reach back into the original.
    $copy->update(['data' => ['body' => 'Changed', 'tone' => 'tip']]);
    expect($block->fresh()->data['body'])->toBe('Listen closely');
});

it('duplicates through the route', function () {
    ['admin' => $admin, 'course' => $course, 'lesson' => $lesson] = outlineFixture();
    $block = makeBlock($lesson->id, 'A');

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/blocks/{$block->id}/duplicate")
        ->assertRedirect();

    expect($lesson->fresh()->blocks)->toHaveCount(2);
});

it('will not duplicate a block belonging to a different course', function () {
    ['admin' => $admin, 'course' => $course] = outlineFixture();
    ['lesson' => $other] = outlineFixture('Elsewhere');
    $foreign = makeBlock($other->id, 'X');

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/blocks/{$foreign->id}/duplicate")
        ->assertNotFound();

    expect($other->fresh()->blocks)->toHaveCount(1);
});

it('refuses duplication and reordering without courses.manage', function () {
    ['course' => $course, 'lesson' => $lesson] = outlineFixture();
    $block = makeBlock($lesson->id, 'A');
    $outsider = actingPeopleAdmin([]);

    $this->actingAs($outsider)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/blocks/{$block->id}/duplicate")
        ->assertForbidden();

    $this->actingAs($outsider)
        ->withoutLocalizationMiddleware()
        ->post("/catalog/courses/{$course->id}/blocks/reorder", [
            'lesson_id' => $lesson->id,
            'block_ids' => [$block->id],
        ])
        ->assertForbidden();
});
