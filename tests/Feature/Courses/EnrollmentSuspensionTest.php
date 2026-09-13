<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SuspendEnrollmentAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Actions\ReserveOfferingSeatAction;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §23 "Student Enrollment".
 *
 * The table carries most of what §23 names, the seat rule exists and locks
 * rows properly, and `CancelEnrollmentAction` already frees a seat. Two things
 * did not hold.
 *
 * **`suspended` was not a status the database could store.** §23 lists six —
 * Active, Pending payment, Pending approval, **Suspended**, Completed,
 * Cancelled — and the enum held `pending`, `approved`, `rejected`, `active`,
 * `completed`, `cancelled`. The word `suspended` appears **nowhere in the
 * codebase**. That matters more than a missing enum value usually would,
 * because §23's own seat rule is written about it:
 *
 *   > Cancelled/**suspended** enrollments should not count as active seats.
 *
 * A rule written about a status the database cannot hold has never once been
 * exercised.
 *
 * **A soft-deleted enrolment held its seat forever.** `EnforceSeatLimitAction`
 * counts through the **query builder** — deliberately, because it needs
 * `lockForUpdate()` — and the query builder knows nothing about `SoftDeletes`.
 * `course_enrollments` soft-deletes under §29, so any removed row kept
 * occupying a place nobody could see.
 *
 * Access needs no new guard, and that is worth stating: every reader —
 * `AuthorizeLessonAccessAction`, `AuthorizeAssessmentAccessAction`,
 * `ListStudentDashboardAction` — asks for `['active', 'approved', 'completed']`
 * **by name**. A status they do not name is denied by construction. An
 * allow-list is safe to extend the vocabulary around; a deny-list would not
 * have been.
 */
uses(RefreshDatabase::class);

function suspendableOffering(int $seatLimit): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Seats '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    $offering = CourseOffering::query()->where('course_id', $course->id)->firstOrFail();
    $offering->seat_limit = $seatLimit;
    $offering->save();

    return ['admin' => $admin, 'course' => $course->fresh(), 'offering' => $offering->fresh()];
}

function suspendableStudent(int $courseId, ?int $offeringId): CourseEnrollment
{
    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Seat', 'last_name' => 'Holder']);

    return app(EnrollSelfLearningAction::class)->execute($user->id, $courseId, $offeringId);
}

it('stores the sixth status §23 names', function () {
    ['course' => $course, 'offering' => $offering] = suspendableOffering(5);
    $enrollment = suspendableStudent($course->id, $offering->id);

    $suspended = app(SuspendEnrollmentAction::class)->execute($enrollment);

    expect((string) $suspended->status)->toBe('suspended')
        // Round-tripped through the database, because the point is that the
        // enum can hold it — not that the model can be assigned it.
        ->and((string) CourseEnrollment::query()->find($enrollment->id)->status)->toBe('suspended');
});

it('releases the seat a suspended enrolment was holding', function () {
    // §23: "Cancelled/suspended enrollments should not count as active seats."
    ['course' => $course, 'offering' => $offering] = suspendableOffering(1);
    $first = suspendableStudent($course->id, $offering->id);

    // The single seat is taken.
    expect(fn () => app(ReserveOfferingSeatAction::class)->execute((int) $offering->id))
        ->toThrow(ValidationException::class);

    app(SuspendEnrollmentAction::class)->execute($first);

    // And now it is not.
    $seat = app(ReserveOfferingSeatAction::class)->execute((int) $offering->id);
    expect($seat['id'])->toBe((int) $offering->id);
});

it('stops a soft-deleted enrolment holding a seat forever', function () {
    // `EnforceSeatLimitAction` counts through the query builder for the row
    // locks, and the query builder does not know about `SoftDeletes`.
    ['course' => $course, 'offering' => $offering] = suspendableOffering(1);
    $enrollment = suspendableStudent($course->id, $offering->id);

    expect(fn () => app(ReserveOfferingSeatAction::class)->execute((int) $offering->id))
        ->toThrow(ValidationException::class);

    $enrollment->delete();

    expect($enrollment->fresh()?->deleted_at)->not->toBeNull('the enrolment should soft-delete, not vanish');

    $seat = app(ReserveOfferingSeatAction::class)->execute((int) $offering->id);
    expect($seat['id'])->toBe((int) $offering->id);
});

it('keeps every trace of the student while they are suspended', function () {
    // §29: "Historical student data must remain intact." Suspension is the
    // reversible one — that is the whole reason to have it rather than cancel.
    ['course' => $course, 'offering' => $offering] = suspendableOffering(5);
    $enrollment = suspendableStudent($course->id, $offering->id);
    $before = DB::table('course_enrollments')->where('id', $enrollment->id)->first();

    app(SuspendEnrollmentAction::class)->execute($enrollment);
    $after = DB::table('course_enrollments')->where('id', $enrollment->id)->first();

    expect($after->deleted_at)->toBeNull()
        ->and((int) $after->unified_student_id)->toBe((int) $before->unified_student_id)
        ->and((int) $after->progress_percentage)->toBe((int) $before->progress_percentage)
        ->and($after->enrolled_at)->toBe($before->enrolled_at);
});

it('denies access without any new guard, because the readers use an allow-list', function () {
    ['course' => $course, 'offering' => $offering] = suspendableOffering(5);
    $enrollment = suspendableStudent($course->id, $offering->id);
    $user = User::query()->whereIn('id', DB::table('students')->pluck('user_id'))->latest('id')->first();

    app(SuspendEnrollmentAction::class)->execute($enrollment);

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get(route('learn.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('enrollments', 0));

    // The reason it works: these read `['active', 'approved', 'completed']` by
    // name rather than excluding a deny-list.
    foreach ([
        'Domains/Courses/Actions/AuthorizeLessonAccessAction.php',
        'Domains/Courses/Actions/ListStudentDashboardAction.php',
    ] as $path) {
        expect((string) file_get_contents(app_path($path)))
            ->toContain("whereIn('status', ['active', 'approved', 'completed'])");
    }
});

it('reinstates only into a seat that is actually free', function () {
    // The seat was released while suspended, so coming back has to compete for
    // it — reinstating into a full offering would put it over its own limit,
    // which is what §23's rule exists to prevent.
    ['course' => $course, 'offering' => $offering] = suspendableOffering(1);
    $first = suspendableStudent($course->id, $offering->id);

    app(SuspendEnrollmentAction::class)->execute($first);

    // Somebody else takes the freed seat.
    $second = suspendableStudent($course->id, $offering->id);
    expect((string) $second->status)->not->toBe('suspended');

    expect(fn () => app(SuspendEnrollmentAction::class)->reinstate($first->fresh()))
        ->toThrow(ValidationException::class);

    // With the seat free again it goes through.
    app(SuspendEnrollmentAction::class)->execute($second);
    $back = app(SuspendEnrollmentAction::class)->reinstate($first->fresh());

    expect((string) $back->status)->toBe('active');
});

it('refuses to suspend something already off the course', function () {
    ['course' => $course, 'offering' => $offering] = suspendableOffering(5);
    $enrollment = suspendableStudent($course->id, $offering->id);
    $enrollment->update(['status' => 'cancelled']);

    expect(fn () => app(SuspendEnrollmentAction::class)->execute($enrollment->fresh()))
        ->toThrow(ValidationException::class);

    // And reinstating something that was never suspended is equally meaningless.
    expect(fn () => app(SuspendEnrollmentAction::class)->reinstate($enrollment->fresh()))
        ->toThrow(ValidationException::class);
});
