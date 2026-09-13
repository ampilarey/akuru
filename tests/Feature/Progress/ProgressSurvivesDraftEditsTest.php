<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\ReorderContentBlocksAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\ContentBlock;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Models\StudentLessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * SPEC §53 "Testing Scope" states one invariant **twice**, under two different
 * headings, which is a fair signal about how much it matters:
 *
 *   > 3. Lesson revision creation
 *   >  - Draft edits do not change what students see.
 *   >  - Student progress references the revision completed.
 *   >  - **Reordering draft blocks does not corrupt historical completed
 *   >    progress.**
 *   >
 *   > 7. Progress tracking
 *   >  - **Completed lesson remains completed after draft block edit/reorder.**
 *   >  - Progress references the correct lesson revision.
 *
 * §28.6 says the same thing as a rule: "Editing or reordering blocks must never
 * change historical progress, scores, attempt snapshots, or student-visible
 * history."
 *
 * This is the load-bearing claim of the whole revision design — the reason
 * immutable revisions exist at all. It was tested from one side:
 * `BlockRequiredStatusTest` proves a later edit cannot change what a published
 * revision *requires*, and `LessonProgressTest` proves progress cannot be
 * recorded without a revision id. **Neither walks a student's completed
 * progress row through an edit** and checks it is still there, still complete,
 * and still pointing at the revision the student actually finished.
 *
 * That gap matters because the failure would be silent and retrospective: an
 * author reorders two blocks in a draft months later and the damage is to
 * history — a completion a student already earned. Nothing on screen says so.
 *
 * The completion is driven **through the student's own routes** (enroll, then
 * `learn.lessons.complete`) rather than by calling the Action, so what is
 * pinned is the path a person actually takes.
 *
 * The fixture is local and deliberately not the one in
 * `SelfLearningEnrollmentTest`: Pest loads every test file into one scope, so
 * borrowing a helper from another file works in a full run and disappears
 * under `--filter`.
 */
function draftEditProgressFixture(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);

    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Progress survives '.uniqid(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Unit', 'created_by' => $admin->id,
    ]);

    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id, 'title' => 'Lesson one', 'created_by' => $admin->id,
    ]);

    // Three blocks, so a reorder has something to move.
    foreach (['First', 'Second', 'Third'] as $body) {
        app(SaveContentBlockAction::class)->execute([
            'lesson_id' => $lesson->id,
            'type' => 'text',
            'data' => ['body' => $body],
            'created_by' => $admin->id,
        ]);
    }

    $revision = app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);

    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Yusuf']);
    app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

    return compact('admin', 'course', 'module', 'lesson', 'revision', 'user');
}

it('keeps a completed lesson completed, on its own revision, after the draft is reordered', function () {
    ['lesson' => $lesson, 'revision' => $revision, 'user' => $user] = draftEditProgressFixture();

    // The student finishes the lesson as it stands, through their own route.
    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('learn.lessons.complete', $lesson))
        ->assertRedirect(route('learn.lessons.show', $lesson));

    $before = StudentLessonProgress::query()->where('lesson_id', $lesson->id)->firstOrFail();

    expect($before->status->value)->toBe('completed')
        ->and($before->lesson_revision_id)->toBe($revision->id);

    // Months later, the author reverses the draft's block order.
    $ids = ContentBlock::query()->where('lesson_id', $lesson->id)
        ->orderBy('position')->pluck('id')->reverse()->values()->all();

    app(ReorderContentBlocksAction::class)->execute($lesson->id, $ids);

    $after = $before->fresh();

    // §28.6: "Editing or reordering blocks must never change historical
    // progress." All three parts are asserted, because each could break on its
    // own: the row could vanish, be reopened, or be re-pointed at a revision
    // the student never saw.
    expect($after)->not->toBeNull()
        ->and($after->status->value)->toBe('completed')
        ->and($after->lesson_revision_id)->toBe($revision->id);
});

it('leaves the published revision untouched when the draft is reordered', function () {
    ['lesson' => $lesson, 'revision' => $revision] = draftEditProgressFixture();

    // What a student who completed it would see on re-opening.
    $snapshotBefore = $revision->fresh()->getAttributes();

    $ids = ContentBlock::query()->where('lesson_id', $lesson->id)
        ->orderBy('position')->pluck('id')->reverse()->values()->all();

    app(ReorderContentBlocksAction::class)->execute($lesson->id, $ids);

    // "Draft edits do not change what students see." The snapshot is the thing
    // students see, so it must be byte-identical — not merely still present.
    expect($revision->fresh()->getAttributes())->toEqual($snapshotBefore);

    // And the draft really did change, so the assertion above is not passing
    // because nothing happened.
    $draftOrder = ContentBlock::query()->where('lesson_id', $lesson->id)
        ->orderBy('position')->pluck('id')->all();

    expect($draftOrder)->toBe($ids);
});
