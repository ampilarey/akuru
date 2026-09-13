<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\StartOrCompleteLessonProgressAction;
use App\Domains\Courses\Actions\SummarizeLessonScoreAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Models\CourseOffering;
use App\Domains\Progress\Actions\ReviewAttemptAction;
use App\Domains\Progress\Actions\SaveActivityAttemptAction;
use App\Domains\Progress\Actions\SubmitActivityAttemptAction;
use App\Domains\Progress\Models\StudentLessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * SPEC §25 "Progress Tracking".
 *
 * Most of §25 is **cleared, not faulted**, and that is worth saying first. The
 * course progress formula is exactly what §25 specifies, it is covered by the
 * unit tests §25 asks for ("Unit tests must cover this calculation"), offering
 * context really is applied — `SyncEnrollmentProgressAction` folds required
 * sessions into the denominator for a student enrolled through an offering —
 * and all four progress statuses exist.
 *
 * Two of the fields §25 names on `student_lesson_progress` were dead:
 *
 * - **`course_offering_id`** — the column exists, the model is fillable,
 *   `RecordLessonProgressAction` accepts it, and its only caller never passed
 *   it. So **every** row was null, including rows belonging to students
 *   enrolled through a live batch. A progress record that cannot say which
 *   batch it belongs to is not a record of that batch, and §25 is explicit
 *   that offering progress is computed "in the context of that offering".
 *
 * - **`score_summary`** — the same shape one level worse:
 *   `'score_summary' => $data['score_summary'] ?? $row->score_summary` was
 *   written to accept a value nobody has ever sent. Every progress row in the
 *   system recorded that a lesson was finished and nothing about how.
 *
 * §25 puts `score_summary` on the row rather than leaving it derivable for the
 * same reason §21 snapshots a question: an activity re-attempted, re-marked or
 * deleted later must not silently rewrite a lesson completed in March.
 */
uses(RefreshDatabase::class);

function progressRecordCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Progress record '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Unit 1', 'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id, 'title' => 'Only lesson', 'created_by' => $admin->id,
    ]);
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id, 'type' => 'text', 'data' => ['body' => 'Body'], 'created_by' => $admin->id,
    ]);
    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    return ['admin' => $admin, 'course' => $course->fresh(), 'lesson' => $lesson->fresh()];
}

function progressRecordStudent(int $courseId, ?int $offeringId): array
{
    $user = User::factory()->create();
    $student = makeStudent(['user_id' => $user->id, 'first_name' => 'Progress', 'last_name' => 'Pupil']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, $courseId, $offeringId);

    return compact('user', 'student', 'enrollment');
}

it('records which offering a lesson was completed under', function () {
    ['course' => $course, 'lesson' => $lesson] = progressRecordCourse();
    $offering = CourseOffering::query()->where('course_id', $course->id)->firstOrFail();
    ['user' => $user, 'enrollment' => $enrollment] = progressRecordStudent($course->id, $offering->id);

    app(StartOrCompleteLessonProgressAction::class)->execute($lesson->id, $user, 'completed');

    $row = StudentLessonProgress::query()->where('enrollment_id', $enrollment->id)->firstOrFail();

    expect((int) $row->course_offering_id)->toBe((int) $offering->id);
});

it('leaves the offering null when the enrolment has none', function () {
    // §25 calls the field nullable, and this is what that means: progress that
    // belongs to no batch. Null here is a fact, not the old defect.
    //
    // Reaching it takes a deliberate step, and that is worth recording: every
    // enrolment made through `EnrollSelfLearningAction` gets an offering, because
    // it falls back to `DefaultSelfLearningOfferingAction` when none is asked
    // for. A null only arises for an enrolment on a course that has no offering
    // at all — an imported or hand-made row.
    ['course' => $course, 'lesson' => $lesson] = progressRecordCourse();
    ['user' => $user, 'enrollment' => $enrollment] = progressRecordStudent($course->id, null);
    $enrollment->course_offering_id = null;
    $enrollment->save();

    app(StartOrCompleteLessonProgressAction::class)->execute($lesson->id, $user, 'completed');

    expect(StudentLessonProgress::query()->where('enrollment_id', $enrollment->id)->value('course_offering_id'))
        ->toBeNull();
});

it('writes a score summary from the activities the lesson actually holds', function () {
    ['admin' => $admin, 'course' => $course, 'lesson' => $lesson] = progressRecordCourse();
    ['user' => $user, 'student' => $student, 'enrollment' => $enrollment] = progressRecordStudent($course->id, null);

    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'title' => 'Match the pairs',
        'pattern' => 'selection',
        'is_required' => true,
        'data' => [
            'options' => [['id' => 'a', 'label' => 'One'], ['id' => 'b', 'label' => 'Two']],
            'correct_ids' => ['a'],
        ],
        'max_score' => 10,
        'created_by' => $admin->id,
    ]);

    $attempt = app(SaveActivityAttemptAction::class)->execute(
        (int) $activity->id,
        (int) $enrollment->id,
        (int) $student->id,
        (int) $course->id,
        ['selected_ids' => ['a']],
    );
    app(SubmitActivityAttemptAction::class)->execute(
        (int) $activity->id,
        (int) $enrollment->id,
        (int) $student->id,
        (int) $course->id,
        ['selected_ids' => ['a']],
    );

    app(StartOrCompleteLessonProgressAction::class)->execute($lesson->id, $user, 'completed');

    $summary = StudentLessonProgress::query()->where('enrollment_id', $enrollment->id)->value('score_summary');

    expect($summary)->not->toBeNull()
        ->and($summary['activities'])->toHaveCount(1)
        ->and((int) $summary['activities'][0]['activity_id'])->toBe((int) $activity->id)
        ->and($summary['activities'][0]['title'])->toBe('Match the pairs')
        ->and($summary['activities'][0]['is_required'])->toBeTrue()
        ->and($summary['percent'])->toBe(100);

    expect($attempt)->toBeArray();
});

it('shows the recorded score back on the course page', function () {
    // A column written and never read is the same defect one step later, so
    // the figure has to surface somewhere a student looks.
    ['admin' => $admin, 'course' => $course, 'lesson' => $lesson] = progressRecordCourse();
    ['user' => $user, 'student' => $student, 'enrollment' => $enrollment] = progressRecordStudent($course->id, null);

    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'title' => 'Quick check',
        'pattern' => 'selection',
        'is_required' => false,
        'data' => [
            'options' => [['id' => 'a', 'label' => 'One'], ['id' => 'b', 'label' => 'Two']],
            'correct_ids' => ['a'],
        ],
        'max_score' => 10,
        'created_by' => $admin->id,
    ]);
    app(SubmitActivityAttemptAction::class)->execute(
        (int) $activity->id,
        (int) $enrollment->id,
        (int) $student->id,
        (int) $course->id,
        ['selected_ids' => ['a']],
    );
    app(StartOrCompleteLessonProgressAction::class)->execute($lesson->id, $user, 'completed');

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get(route('learn.courses.show', $course->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Learn/Show')
            ->where('modules.0.lessons.0.score', 10)
            ->where('modules.0.lessons.0.max_score', 10));
});

it('leaves the summary null for a lesson with nothing to score', function () {
    // A lesson of pure reading has no score. Writing `{score: null}` on every
    // such row would make the column look populated while saying nothing.
    ['course' => $course, 'lesson' => $lesson] = progressRecordCourse();
    ['user' => $user, 'enrollment' => $enrollment] = progressRecordStudent($course->id, null);

    app(StartOrCompleteLessonProgressAction::class)->execute($lesson->id, $user, 'completed');

    expect(StudentLessonProgress::query()->where('enrollment_id', $enrollment->id)->value('score_summary'))
        ->toBeNull();
});

it('keeps the summary the lesson was completed with when the activity is re-marked', function () {
    // This is the whole reason §25 stores the figure instead of deriving it,
    // and the same argument §21 makes for snapshotting a question: the row is a
    // record of what happened. A teacher raising a mark in June must not
    // rewrite a lesson a student completed in March.
    ['admin' => $admin, 'course' => $course, 'lesson' => $lesson] = progressRecordCourse();
    ['user' => $user, 'student' => $student, 'enrollment' => $enrollment] = progressRecordStudent($course->id, null);

    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'title' => 'Written answer',
        'pattern' => 'teacher_marked',
        'is_required' => false,
        // `submission_kind` is passed explicitly because `SaveActivityAction`
        // crashes without it — see the §25 note in STATUS.md. Out of scope
        // here (rule 1); recorded rather than fixed in this slice.
        'data' => ['prompt' => 'Write a paragraph.', 'submission_kind' => 'written'],
        'max_score' => 10,
        'created_by' => $admin->id,
    ]);
    app(SaveActivityAttemptAction::class)->execute(
        (int) $activity->id,
        (int) $enrollment->id,
        (int) $student->id,
        (int) $course->id,
        ['text' => 'My answer'],
    );
    $submitted = app(SubmitActivityAttemptAction::class)->execute(
        (int) $activity->id,
        (int) $enrollment->id,
        (int) $student->id,
        (int) $course->id,
        ['text' => 'My answer'],
    );
    app(ReviewAttemptAction::class)->execute('activity', (int) $submitted['attempt']['id'], [
        'score' => 4, 'max_score' => 10,
    ], $admin->id);

    app(StartOrCompleteLessonProgressAction::class)->execute($lesson->id, $user, 'completed');
    $atCompletion = StudentLessonProgress::query()->where('enrollment_id', $enrollment->id)->value('score_summary');

    app(ReviewAttemptAction::class)->execute('activity', (int) $submitted['attempt']['id'], [
        'score' => 9, 'max_score' => 10,
    ], $admin->id);

    $now = StudentLessonProgress::query()->where('enrollment_id', $enrollment->id)->value('score_summary');

    expect((float) $atCompletion['score'])->toBe(4.0)
        ->and((float) $now['score'])->toBe(4.0)
        // The live attempt did move; the record of the completion did not.
        ->and(app(SummarizeLessonScoreAction::class)->execute($lesson->fresh(), (int) $enrollment->id)['score'])
        ->toBe(9.0);
});

it('does not churn the summary when the lesson is merely opened again', function () {
    ['admin' => $admin, 'course' => $course, 'lesson' => $lesson] = progressRecordCourse();
    ['user' => $user, 'student' => $student, 'enrollment' => $enrollment] = progressRecordStudent($course->id, null);

    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'title' => 'Quick check',
        'pattern' => 'selection',
        'is_required' => false,
        'data' => [
            'options' => [['id' => 'a', 'label' => 'One']],
            'correct_ids' => ['a'],
        ],
        'max_score' => 5,
        'created_by' => $admin->id,
    ]);
    app(SaveActivityAttemptAction::class)->execute(
        (int) $activity->id,
        (int) $enrollment->id,
        (int) $student->id,
        (int) $course->id,
        ['selected_ids' => ['a']],
    );
    app(SubmitActivityAttemptAction::class)->execute(
        (int) $activity->id,
        (int) $enrollment->id,
        (int) $student->id,
        (int) $course->id,
        ['selected_ids' => ['a']],
    );

    app(StartOrCompleteLessonProgressAction::class)->execute($lesson->id, $user, 'completed');
    $recordedAt = StudentLessonProgress::query()->where('enrollment_id', $enrollment->id)
        ->value('score_summary')['recorded_at'];

    // Opening a completed lesson posts `in_progress`, which the writer already
    // refuses to downgrade. It must not rewrite the summary either.
    app(StartOrCompleteLessonProgressAction::class)->execute($lesson->id, $user, 'in_progress');

    expect(StudentLessonProgress::query()->where('enrollment_id', $enrollment->id)
        ->value('score_summary')['recorded_at'])->toBe($recordedAt);
});
