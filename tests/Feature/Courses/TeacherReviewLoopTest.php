<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Actions\SubmitActivityAttemptAction;
use App\Domains\Progress\Enums\ActivityAttemptStatus;
use App\Domains\Progress\Models\ActivityAttempt;
use App\Support\Authorization\RoleGrants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/**
 * Work a machine cannot mark, marked — the whole loop.
 *
 * SPEC §36 lists thirteen things a teacher must be able to do, and six of them
 * are this one screen: *view pending submissions · open student submissions ·
 * give score · give written feedback · mark passed/failed · request
 * resubmission*. A `teacher_marked` activity is the only pattern whose attempt
 * lands `submitted` rather than `scored`, so it is the only path where any of
 * those six mean anything.
 *
 * Both ends had tests. `ActivityPatternTest` proves the engine declines to
 * score a teacher-marked attempt; `CatalogReportsTest` proves the queue screen
 * renders. The queue's `academic_year_id` filter was fixed (STATUS §5dw)
 * without anything walking the queue it feeds. **Nobody had ever taken one
 * piece of work from a student's hands to a teacher's and back**, which is the
 * fourth time this session that two covered halves turned out to have an
 * untested join between them.
 */
function reviewLoopCourse(): object
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Review loop '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    return $course->fresh();
}

/**
 * @return array{user: User, activity: object, attempt: ActivityAttempt}
 */
function reviewLoopSubmission(): array
{
    $course = reviewLoopCourse();

    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Write a sentence',
        'pattern' => 'teacher_marked',
        'max_score' => 5,
        'data' => ['prompt' => 'Use a sun letter.', 'submission_kind' => 'written'],
    ]);

    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Hands', 'last_name' => 'In']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, (int) $course->id, null);

    app(SubmitActivityAttemptAction::class)->execute(
        (int) $activity->id,
        (int) $enrollment->id,
        (int) $enrollment->unified_student_id,
        (int) $course->id,
        ['text' => 'The sun rises over the letter seen.'],
    );

    $attempt = ActivityAttempt::query()->where('activity_id', $activity->id)->firstOrFail();

    return ['user' => $user, 'activity' => $activity, 'attempt' => $attempt];
}

it('carries work from a student to a marker and the mark back again', function () {
    ['user' => $user, 'activity' => $activity, 'attempt' => $attempt] = reviewLoopSubmission();

    // 1. The engine declines to mark it. Without this the queue would be empty
    //    and every assertion below would pass by having nothing to do.
    expect($attempt->status)->toBe(ActivityAttemptStatus::Submitted)
        ->and($attempt->score)->toBeNull();

    // 2. It is waiting on the marker's screen, with the student's own words on
    //    it — §36's "open student submissions", not a row count.
    $marker = actingPeopleAdmin(['courses.manage']);
    $queue = $this->withoutLocalizationMiddleware()
        ->actingAs($marker)
        ->get(route('catalog.reviews.index'))
        ->assertOk();

    $rows = collect($queue->viewData('page')['props']['rows']);
    $row = $rows->firstWhere('id', $attempt->id);

    expect($row)->not->toBeNull()
        ->and($row['title'])->toBe('Write a sentence')
        ->and($row['student_name'])->toBe('Hands In')
        ->and($row['answers']['text'])->toBe('The sun rises over the letter seen.');

    // 3. The marker gives a score and a sentence.
    $this->withoutLocalizationMiddleware()
        ->actingAs($marker)
        ->post(route('catalog.reviews.store'), [
            'kind' => 'activity',
            'attempt_id' => $attempt->id,
            'score' => 4,
            'max_score' => 5,
            'feedback' => 'Well argued, mind the hamza.',
        ])
        ->assertRedirect(route('catalog.reviews.index'));

    // 4. It leaves the queue.
    $after = $this->withoutLocalizationMiddleware()
        ->actingAs($marker)
        ->get(route('catalog.reviews.index'))
        ->assertOk();

    expect(collect($after->viewData('page')['props']['rows'])->firstWhere('id', $attempt->id))->toBeNull();

    // 5. And the student opens the same page they submitted on and sees both.
    //    The columns are asserted through the screen rather than the table,
    //    because a mark nobody is shown is not a mark.
    $student = $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.activities.show', $activity->id))
        ->assertOk();

    $shown = $student->viewData('page')['props']['attempt'];

    expect($shown['status'])->toBe('scored')
        ->and($shown['score'])->toBe(4)
        ->and($shown['max_score'])->toBe(5)
        ->and($shown['feedback'])->toBe('Well argued, mind the hamza.')
        ->and($shown['reviewed_at'])->not->toBeNull();
});

/**
 * The finding the walk above was written to look for — and its answer.
 *
 * `/catalog/reviews` is titled "Teacher review", answers six of §36's thirteen
 * teacher abilities, and was gated on `courses.manage` — which the `teacher`
 * role does not hold. A teacher who set written work could not see it come
 * in. It was pinned rather than fixed because the fix was a decision: the
 * queue was school-wide and `course_instructor` had no writer, so "my own
 * courses" could not be said (OWNER_ACTIONS item 16).
 *
 * Decided 2026-10-03, "teachers mark only their own courses" (C16 slice N6,
 * STATUS §5ob): the teacher holds `courses.review`, the queue admits them
 * and narrows to the courses their instructor profile is assigned — and a
 * teacher nobody has assigned yet sees an empty queue that says so, not a
 * 403. The full loop is in `TeacherMarksOwnCoursesTest`.
 */
it('admits a teacher to the review queue, narrowed to their own courses — none yet, so empty and said', function () {
    $teacher = User::factory()->create();
    $role = Role::findOrCreate('teacher', 'web');
    $role->syncPermissions(RoleGrants::teacher());
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $teacher->assignRole($role);

    expect($teacher->fresh()->can('courses.manage'))->toBeFalse()
        ->and($teacher->fresh()->can('courses.review'))->toBeTrue();

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacher->fresh())
        ->get(route('catalog.reviews.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rows', 0)
            ->where('scope.own_courses', true)
            ->where('scope.course_count', 0));
});
