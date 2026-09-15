<?php

use App\Domains\Courses\Actions\CancelEnrollmentAction;
use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\EnrollUnifiedStudentInOfferingAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A published course and somebody who can enrol on it.
 *
 * Local rather than borrowed from `SelfLearningEnrollmentTest`: that file's
 * helper is declared at file scope, so a test file that ran before it loaded
 * would die on an undefined function rather than on anything to do with
 * enrolment.
 */
function reEnrolFixture(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);

    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Second chances',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Aminath']);

    return ['admin' => $admin, 'course' => $course->fresh(), 'user' => $user];
}

/**
 * A family who was refunded could never come back.
 *
 * Both enrolment Actions ask "is there a live enrolment here?" — deliberately
 * skipping `rejected` and `cancelled` rows, so that somebody who withdrew, or
 * whose payment was refunded, may enrol again. The database disagreed: the
 * unique key `(student_id, course_id, term_key)` was written before `cancelled`
 * existed as a status and does not exclude it, so the insert that follows that
 * decision hits a duplicate key and the learner gets a 500.
 *
 * The two halves were each plausible alone, which is why this survived: the
 * guard reads as generosity, the key reads as hygiene, and nothing brought them
 * together until somebody refunded a payment and tried to enrol again.
 */
it('lets a student enrol again after their enrolment was cancelled', function () {
    ['course' => $course, 'user' => $user] = reEnrolFixture();

    $first = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);
    app(CancelEnrollmentAction::class)->execute((int) $first->id);

    expect($first->fresh()->status)->toBe('cancelled');

    $second = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

    expect($second->status)->toBe('active')
        ->and($second->payment_status)->toBe('not_required')
        // One enrolment per student per course per term is what the unique key
        // says, and reviving keeps that true — a second row would be the same
        // duplicate under a different name.
        ->and(CourseEnrollment::query()
            ->where('course_id', $course->id)
            ->where('student_id', $first->student_id)
            ->count())->toBe(1);
});

it('keeps the progress a revived enrolment already earned', function () {
    ['course' => $course, 'user' => $user] = reEnrolFixture();

    $first = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);
    $first->forceFill(['progress_percentage' => 60])->save();

    app(CancelEnrollmentAction::class)->execute((int) $first->id);
    $again = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

    // `student_lesson_progress` is keyed by student and lesson and survives a
    // cancellation untouched, so zeroing the enrolment's rollup would have the
    // catalog say 0% over lessons that are visibly complete.
    expect($again->progress_percentage)->toBe(60);
});

it('lets the office enrol a student on an offering again after cancelling them', function () {
    ['course' => $course, 'user' => $user, 'admin' => $admin] = reEnrolFixture();

    $offering = CourseOffering::query()->create([
        'course_id' => $course->id,
        'title' => 'Second chance',
        'slug' => 'second-chance',
        'delivery_mode' => 'self_learning',
        'status' => 'open',
        'pin_mode' => 'latest',
    ]);

    $first = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);
    $studentId = (int) $first->unified_student_id;
    app(CancelEnrollmentAction::class)->execute((int) $first->id);

    $again = app(EnrollUnifiedStudentInOfferingAction::class)
        ->execute($studentId, (int) $course->id, (int) $offering->id, $admin->id);

    expect($again->status)->toBe('active')
        ->and((int) $again->course_offering_id)->toBe((int) $offering->id)
        ->and(CourseEnrollment::query()->where('course_id', $course->id)->count())->toBe(1);
});

it('enrols again over a soft-deleted enrolment', function () {
    ['course' => $course, 'user' => $user] = reEnrolFixture();

    $first = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);
    $first->delete();

    expect(CourseEnrollment::query()->find($first->id))->toBeNull();

    // A soft-deleted row still occupies the unique key, so a lookup that cannot
    // see it crashes on the insert instead of finding it. SPEC §29 keeps the
    // row precisely so the record is not rewritten; enrolling again must
    // therefore bring it back rather than add a second one.
    $again = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

    expect($again->id)->toBe($first->id)
        ->and($again->status)->toBe('active')
        ->and($again->deleted_at)->toBeNull();
});

it('does not disturb an enrolment that is already live', function () {
    ['course' => $course, 'user' => $user] = reEnrolFixture();

    $first = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);
    $first->forceFill(['progress_percentage' => 42])->save();

    $again = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

    expect($again->id)->toBe($first->id)
        ->and($again->progress_percentage)->toBe(42)
        ->and($again->enrolled_at->toDateTimeString())->toBe($first->enrolled_at->toDateTimeString());
});
