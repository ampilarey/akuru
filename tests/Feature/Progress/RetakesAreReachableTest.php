<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Actions\ResolveRetakeStateAction;
use App\Domains\Progress\Actions\SubmitActivityAttemptAction;
use App\Domains\Progress\Models\ActivityAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * A second go exists, and the person it is for can reach it.
 *
 * ## The defect
 *
 * The retake machinery was complete except for the learner. Authors set
 * `retakes_allowed` and `retake_limit` — the authoring forms default to 3 for
 * an activity and 2 for an assessment. Both submit paths enforce the policy.
 * `nextNumber()` exists on both to number attempt two, and
 * `SubmitActivityAttemptAction` creates it. The teacher's revision report says
 * *"Retry the weak item when retakes remain; otherwise review with a teacher."*
 *
 * **And no learner could ever start a second attempt.** Both players compute
 * `submitted = attempt && attempt.status !== 'in_progress'` and disable every
 * input and button on it, permanently, with no control to begin again.
 *
 * Same shape as the status columns in STATUS §5dq: configured, enforced,
 * reported on, and unreachable. Found by running a smoke walk twice — the
 * second run could not answer the question the first one had (STATUS §5ed).
 *
 * ## What these tests pin
 *
 * The **screen**, not the column. A retake that the server would allow and the
 * page never offers is exactly the defect being fixed, so every test here
 * asserts what the player is told.
 */
function retakeCourse(): object
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Retakes '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    return $course->fresh();
}

/**
 * @param  array<string, mixed>  $settings
 * @return array{user: User, activity: object, enrollment: object}
 */
function retakeFixture(array $settings = []): array
{
    $course = retakeCourse();

    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Pick the sun letter',
        'pattern' => 'selection',
        'max_score' => 1,
        'data' => [
            'prompt' => 'Which is a sun letter?',
            'options' => [['id' => 'a', 'label' => 'Right'], ['id' => 'b', 'label' => 'Wrong']],
            'correct_ids' => ['a'],
        ],
        'settings' => $settings,
    ]);

    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Second', 'last_name' => 'Go']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, (int) $course->id, null);

    return compact('user', 'activity', 'enrollment');
}

function submitRetakeAttempt(object $activity, object $enrollment, string $choice = 'b'): void
{
    app(SubmitActivityAttemptAction::class)->execute(
        (int) $activity->id,
        (int) $enrollment->id,
        (int) $enrollment->unified_student_id,
        (int) $activity->course_id,
        ['selected_ids' => [$choice]],
    );
}

it('offers a second go on the page, once a first has been used', function () {
    ['user' => $user, 'activity' => $activity, 'enrollment' => $enrollment] = retakeFixture(['retake_limit' => 3]);

    // Before anything is submitted there is nothing to retake, and offering
    // one would be nonsense.
    $fresh = $this->withoutLocalizationMiddleware()->actingAs($user)
        ->get(route('learn.activities.show', $activity->id))->assertOk();

    expect($fresh->viewData('page')['props']['retake']['can_retake'])->toBeFalse();

    submitRetakeAttempt($activity, $enrollment);

    $after = $this->withoutLocalizationMiddleware()->actingAs($user)
        ->get(route('learn.activities.show', $activity->id))->assertOk();

    $retake = $after->viewData('page')['props']['retake'];

    expect($retake['can_retake'])->toBeTrue()
        ->and($retake['used'])->toBe(1)
        ->and($retake['limit'])->toBe(3)
        ->and($retake['remaining'])->toBe(2);
});

it('actually records a second attempt when the student takes it', function () {
    ['activity' => $activity, 'enrollment' => $enrollment] = retakeFixture(['retake_limit' => 3]);

    submitRetakeAttempt($activity, $enrollment, 'b');
    submitRetakeAttempt($activity, $enrollment, 'a');

    $attempts = ActivityAttempt::query()
        ->where('activity_id', $activity->id)
        ->orderBy('attempt_number')
        ->get();

    // Two rows, numbered, and the second one is the one that counts.
    expect($attempts)->toHaveCount(2)
        ->and($attempts[0]->attempt_number)->toBe(1)
        ->and($attempts[0]->score)->toBe(0)
        ->and($attempts[1]->attempt_number)->toBe(2)
        ->and($attempts[1]->score)->toBe(1);
});

it('stops offering when the goes run out, and refuses one taken anyway', function () {
    ['user' => $user, 'activity' => $activity, 'enrollment' => $enrollment] = retakeFixture(['retake_limit' => 2]);

    submitRetakeAttempt($activity, $enrollment);
    submitRetakeAttempt($activity, $enrollment);

    $page = $this->withoutLocalizationMiddleware()->actingAs($user)
        ->get(route('learn.activities.show', $activity->id))->assertOk();

    $retake = $page->viewData('page')['props']['retake'];

    expect($retake['can_retake'])->toBeFalse()
        ->and($retake['remaining'])->toBe(0);

    // And the screen's answer is the server's answer. The two used to be
    // separate counts; a button offered and then refused is worse than no
    // button, so they are now one reader (rule 11).
    expect(fn () => submitRetakeAttempt($activity, $enrollment))
        ->toThrow(ValidationException::class);
});

it('offers nothing when the author switched retakes off', function () {
    ['user' => $user, 'activity' => $activity, 'enrollment' => $enrollment] = retakeFixture([
        'retakes_allowed' => false,
    ]);

    submitRetakeAttempt($activity, $enrollment);

    $page = $this->withoutLocalizationMiddleware()->actingAs($user)
        ->get(route('learn.activities.show', $activity->id))->assertOk();

    expect($page->viewData('page')['props']['retake']['can_retake'])->toBeFalse();

    expect(fn () => submitRetakeAttempt($activity, $enrollment))
        ->toThrow(ValidationException::class);
});

it('says "as many as you like" rather than "none left" when no limit is set', function () {
    // `remaining: null` means no limit, which is not zero and must not render
    // as it. A screen that says "0 left" when the answer is "unlimited" is
    // worse than saying nothing.
    ['user' => $user, 'activity' => $activity, 'enrollment' => $enrollment] = retakeFixture([]);

    submitRetakeAttempt($activity, $enrollment);

    $retake = $this->withoutLocalizationMiddleware()->actingAs($user)
        ->get(route('learn.activities.show', $activity->id))->assertOk()
        ->viewData('page')['props']['retake'];

    expect($retake['limit'])->toBeNull()
        ->and($retake['remaining'])->toBeNull()
        ->and($retake['can_retake'])->toBeTrue();
});

it('counts one learner\'s goes and not another\'s', function () {
    ['activity' => $activity, 'enrollment' => $enrollment] = retakeFixture(['retake_limit' => 2]);

    submitRetakeAttempt($activity, $enrollment);
    submitRetakeAttempt($activity, $enrollment);

    // A second learner on the same activity starts with a full allowance. A
    // retake counter scoped to the activity rather than the enrolment would
    // lock a classmate out because somebody else had two goes.
    $other = User::factory()->create();
    makeStudent(['user_id' => $other->id, 'first_name' => 'Somebody', 'last_name' => 'Else']);
    app(EnrollSelfLearningAction::class)->execute($other->id, (int) $activity->course_id, null);

    $page = $this->withoutLocalizationMiddleware()->actingAs($other)
        ->get(route('learn.activities.show', $activity->id))->assertOk();

    $retake = $page->viewData('page')['props']['retake'];

    expect($retake['used'])->toBe(0)
        ->and($retake['remaining'])->toBe(2);
});

it('gives an assessment its retake state too', function () {
    // The other half of the same defect: the assessment player was frozen by
    // the same rule, and `retake_limit` has always been enforced there.
    $state = app(ResolveRetakeStateAction::class)->forAssessment(1, null, 1, 2);

    expect($state)->toHaveKeys(['used', 'limit', 'allowed', 'remaining', 'can_retake'])
        ->and($state['used'])->toBe(0)
        ->and($state['limit'])->toBe(2);
});
