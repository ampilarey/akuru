<?php

use App\Domains\Courses\Actions\ActivateEnrollmentAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Enums\DeliveryMode;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * SPEC §23's seat rule was enforced where a family enrols and skipped where an
 * administrator changes their mind.
 *
 * `AdminEnrollmentController::activate()` was `$enrollment->update(['status' =>
 * 'active'])`. The statuses that occupy a seat are `active`, `approved`,
 * `pending` and `completed`, so `rejected`, `cancelled` and `suspended` do not
 * — and the Blade screen offers "Activate enrolment" on anything that is not
 * already active, a rejected one included. One click moved a row into an
 * occupying status without ever consulting the limit, and a full class could be
 * oversold from the admin screen one reinstatement at a time.
 */
function seatTestOffering(int $seats): CourseOffering
{
    $course = Course::factory()->create(['workflow_status' => 'published', 'status' => 'open']);

    return CourseOffering::query()->create([
        'course_id' => $course->id,
        'title' => 'Self learning',
        'slug' => 'self-learning-'.$course->id,
        'delivery_mode' => DeliveryMode::SelfLearning,
        'status' => 'open',
        'pin_mode' => 'latest',
        'seat_limit' => $seats,
    ]);
}

function seatTestEnrollment(CourseOffering $offering, string $status): CourseEnrollment
{
    $user = User::factory()->create();
    $student = makeCourseStudent([
        'user_id' => $user->id,
        'first_name' => 'Seat',
        'last_name' => 'Test'.$user->id,
        'dob' => now()->subYears(12),
        'gender' => 'female',
    ]);

    return CourseEnrollment::create([
        'unified_student_id' => $student->id,
        'course_id' => $offering->course_id,
        'course_offering_id' => $offering->id,
        'status' => $status,
        'payment_status' => 'not_required',
        'enrollment_type' => 'free',
        'created_by_user_id' => $user->id,
    ]);
}

it('refuses to reinstate a rejected enrolment onto a full offering', function () {
    $offering = seatTestOffering(1);
    seatTestEnrollment($offering, 'active');          // the one seat, taken
    $rejected = seatTestEnrollment($offering, 'rejected');

    expect(fn () => app(ActivateEnrollmentAction::class)->execute($rejected))
        ->toThrow(ValidationException::class);

    // And it is still rejected — a refused activation must not half-apply.
    expect($rejected->fresh()->status)->toBe('rejected');
});

it('still activates a pending enrolment on an exactly full offering', function () {
    // The distinction that makes the rule usable: `pending` already occupies a
    // seat, so activation moves it between two occupying statuses. Charging it
    // for a second seat would refuse every legitimate activation on a class
    // that is exactly full — which is most of them.
    $offering = seatTestOffering(1);
    $pending = seatTestEnrollment($offering, 'pending');

    app(ActivateEnrollmentAction::class)->execute($pending);

    expect($pending->fresh()->status)->toBe('active');
});

it('reinstates a rejected enrolment when a seat is free', function () {
    $offering = seatTestOffering(2);
    seatTestEnrollment($offering, 'active');
    $rejected = seatTestEnrollment($offering, 'rejected');

    app(ActivateEnrollmentAction::class)->execute($rejected);

    expect($rejected->fresh()->status)->toBe('active')
        ->and($rejected->fresh()->enrolled_at)->not->toBeNull();
});

it('leaves an already active enrolment alone', function () {
    $offering = seatTestOffering(1);
    $active = seatTestEnrollment($offering, 'active');

    app(ActivateEnrollmentAction::class)->execute($active);

    expect($active->fresh()->status)->toBe('active');
});
