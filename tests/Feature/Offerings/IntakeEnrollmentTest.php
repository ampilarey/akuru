<?php

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Actions\SaveCourseOfferingAction;
use App\Domains\Offerings\Actions\SaveOfferingSessionAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * 1B's definition of done: *"Student enrollment can link to an offering"*
 * and *"Seat limits are enforced safely"*. Both held through the actions;
 * no screen let a learner choose an offering, so every scheduled batch had
 * a roster only a seeder could fill (1B audit D1, STATUS §5fh). Now the
 * learner catalog lists a course's open intakes with the seats each has
 * left, and enrolling names one.
 */
function intakeCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Evening Arabic',
        'subject_id' => CourseSubject::query()->where('slug', 'arabic')->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    $intake = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'October batch',
        'delivery_mode' => 'face_to_face',
        'status' => 'open',
        'seat_limit' => 1,
    ]);
    app(SaveOfferingSessionAction::class)->execute([
        'course_offering_id' => $intake->id,
        'title' => 'First evening',
        'session_type' => 'face_to_face',
        'starts_at' => now()->addDays(3)->setTime(19, 0)->toDateTimeString(),
        'location_name' => 'Room 2',
        'created_by' => $admin->id,
    ]);

    return compact('admin', 'course', 'intake');
}

function intakeStudent(string $name): User
{
    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => $name]);

    return $user;
}

it('lists a course\'s open intakes with seats left, enrols into the chosen one, and holds the seat', function () {
    ['course' => $course, 'intake' => $intake] = intakeCourse();
    $first = intakeStudent('Aishath');

    $this->withoutLocalizationMiddleware()->actingAs($first)
        ->get(route('learn.catalog'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Learn/Catalog')
            ->where('rows.0.title', 'Evening Arabic')
            ->where('rows.0.intakes.0.title', 'October batch')
            ->where('rows.0.intakes.0.delivery_mode', 'face_to_face')
            ->where('rows.0.intakes.0.seats_left', 1)
            ->where('rows.0.intakes.0.full', false)
            ->where('rows.0.intakes.0.next_session.title', 'First evening'));

    $this->withoutLocalizationMiddleware()->actingAs($first)
        ->post(route('learn.courses.enroll', $course->id), ['offering_id' => $intake->id])
        ->assertRedirect(route('learn.courses.show', $course->id));

    $enrollment = CourseEnrollment::query()->where('course_id', $course->id)->sole();
    expect((int) $enrollment->course_offering_id)->toBe((int) $intake->id);

    // The course page says which intake, and lists its session.
    $this->withoutLocalizationMiddleware()->actingAs($first)
        ->get(route('learn.courses.show', $course->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('offering.title', 'October batch')
            ->where('upcoming_sessions.0.title', 'First evening'));

    // The one seat is gone: the next learner sees it full and is refused.
    $second = intakeStudent('Hassan');
    $this->withoutLocalizationMiddleware()->actingAs($second)
        ->get(route('learn.catalog'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.intakes.0.seats_left', 0)
            ->where('rows.0.intakes.0.full', true));
    $this->withoutLocalizationMiddleware()->actingAs($second)
        ->post(route('learn.courses.enroll', $course->id), ['offering_id' => $intake->id])
        // The seat lock refuses with a validation error, back to the catalog.
        ->assertRedirect(route('learn.catalog'))
        ->assertSessionHasErrors();
    expect(CourseEnrollment::query()->where('course_id', $course->id)->count())->toBe(1);
});

it('refuses an intake that belongs to another course', function () {
    ['course' => $course] = intakeCourse();
    $other = intakeCourse();
    $student = intakeStudent('Mariyam');

    $this->withoutLocalizationMiddleware()->actingAs($student)
        ->post(route('learn.courses.enroll', $course->id), ['offering_id' => $other['intake']->id])
        ->assertSessionHasErrors('offering_id');
    expect(CourseEnrollment::query()->count())->toBe(0);
});
