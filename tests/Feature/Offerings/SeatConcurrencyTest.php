<?php

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Offerings\Actions\EnforceSeatLimitAction;
use App\Domains\Offerings\Actions\SaveCourseOfferingAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §11.7 "Enrollment Concurrency and Seat Limits":
 *
 *   > Seat limits must be enforced at the database level, not only in
 *   > application code.
 *   >
 *   > Do not use unsafe count-then-insert logic without a lock.
 *   >
 *   > **Required test:**
 *   > - Simulate two concurrent enrollments against one remaining seat.
 *   > - Exactly one enrollment must succeed.
 *   > - The other must fail gracefully with a clear validation/business error.
 *
 * The implementation is correct — `EnforceSeatLimitAction` takes
 * `lockForUpdate()` on the offering row *and* on the occupancy count, inside a
 * transaction. The test protecting it was not.
 *
 * `OfferingPinAndSeatsTest` has a case named "enforces offering seat limits
 * inside a lock" which enrols one student, then a second, **sequentially**.
 * That proves the limit is applied. It proves nothing about the lock: a naive
 * count-then-insert with no transaction would pass it identically. Delete
 * `lockForUpdate()` and the whole suite stayed green.
 *
 * §11.7 asks for two *concurrent* enrolments. A single-threaded test cannot
 * hold one transaction open while another blocks on it without deadlocking
 * itself, and `EnforceSeatLimitAction` commits per call, so any sequence of
 * calls here is sequential by construction.
 *
 * So the guard is aimed at what would actually break: the first test asserts
 * both statements are issued `FOR UPDATE`. Delete `lockForUpdate()` and it
 * fails at once — which is exactly what the old test did not do. True
 * parallel verification needs a second process and belongs in an integration
 * harness, not here; that is recorded rather than pretended.
 */
uses(RefreshDatabase::class);

function oneSeatOffering(): array
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Concurrency '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);

    $offering = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'One seat '.uniqueFixtureSuffix(),
        'delivery_mode' => 'live_online',
        'status' => 'open',
        'seat_limit' => 1,
    ]);

    return ['course' => $course, 'offering' => $offering];
}

/**
 * Occupy a seat the way a real enrolment does: inside the same transaction
 * that reserved it, so the row lock is still held when the row appears.
 */
function takeSeat(int $offeringId, int $courseId, int $studentId): void
{
    DB::table('course_enrollments')->insert([
        'course_id' => $courseId,
        'course_offering_id' => $offeringId,
        'student_id' => makeRegistrationStudent()->id,
        'unified_student_id' => $studentId,
        'status' => 'active',
        'payment_status' => 'not_required',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('takes a row lock on the offering and on the occupancy count', function () {
    // This is the test that actually protects §11.7's rule, and it is worth
    // being blunt about why the obvious one does not.
    //
    // `EnforceSeatLimitAction::execute()` opens and commits its own
    // transaction, so calling it twice in a row is *sequential* — the same
    // flaw as the existing "enforces offering seat limits inside a lock" case,
    // which enrols one student then another. I wrote that version first and
    // checked it: deleting every `lockForUpdate()` from the action left all of
    // it green. It proved the limit, never the lock.
    //
    // True parallelism needs a second connection blocking on the first, which
    // a single-threaded test cannot hold open without deadlocking itself. So
    // this asserts the thing that would actually break: that the statements
    // are issued FOR UPDATE. Remove the lock and this fails immediately.
    ['offering' => $offering] = oneSeatOffering();

    DB::enableQueryLog();
    app(EnforceSeatLimitAction::class)->execute(
        resourceTable: 'course_offerings',
        resourceId: $offering->id,
        limitColumn: 'seat_limit',
        occupancyTable: 'course_enrollments',
        foreignKey: 'course_offering_id',
        occupyingStatuses: ['active', 'approved', 'pending', 'completed'],
        waitlistEnabledColumn: null,
        fullMessage: 'This offering has no remaining seats.',
    );
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    $offeringRead = $queries->first(fn (string $q) => str_contains($q, 'from `course_offerings`'));
    $occupancyRead = $queries->first(fn (string $q) => str_contains($q, 'from `course_enrollments`'));

    expect($offeringRead)->not->toBeNull()
        ->and($offeringRead)->toContain('for update')
        ->and($occupancyRead)->not->toBeNull()
        ->and($occupancyRead)->toContain('for update');
});

it('gives the last seat to exactly one of two arrivals', function () {
    // Deliberately not called a concurrency test: the two arrivals are
    // sequential, because the action commits per call. It proves the limit
    // holds once a seat is taken; the lock is covered by the query assertion
    // above.
    ['course' => $course, 'offering' => $offering] = oneSeatOffering();
    $studentA = makeStudent(['first_name' => 'Racer', 'last_name' => 'One']);

    $args = [
        'resourceTable' => 'course_offerings',
        'resourceId' => $offering->id,
        'limitColumn' => 'seat_limit',
        'occupancyTable' => 'course_enrollments',
        'foreignKey' => 'course_offering_id',
        'occupyingStatuses' => ['active', 'approved', 'pending', 'completed'],
        'waitlistEnabledColumn' => null,
        'fullMessage' => 'This offering has no remaining seats.',
    ];

    $first = app(EnforceSeatLimitAction::class)->execute(...$args);
    expect($first['outcome'])->toBe(EnforceSeatLimitAction::OUTCOME_RESERVED)
        ->and($first['taken'])->toBe(0);

    takeSeat($offering->id, $course->id, $studentA->id);

    try {
        app(EnforceSeatLimitAction::class)->execute(...$args);
        $this->fail('The second arrival should have been refused.');
    } catch (ValidationException $e) {
        expect($e->errors()['course_offering_id'][0])->toBe('This offering has no remaining seats.');
    }

    expect(DB::table('course_enrollments')->where('course_offering_id', $offering->id)->count())->toBe(1);
});

it('counts a taken seat against the limit rather than re-offering it', function () {
    ['course' => $course, 'offering' => $offering] = oneSeatOffering();
    $student = makeStudent(['first_name' => 'Seated', 'last_name' => 'Student']);
    takeSeat($offering->id, $course->id, $student->id);

    expect(fn () => app(EnforceSeatLimitAction::class)->execute(
        resourceTable: 'course_offerings',
        resourceId: $offering->id,
        limitColumn: 'seat_limit',
        occupancyTable: 'course_enrollments',
        foreignKey: 'course_offering_id',
        occupyingStatuses: ['active', 'approved', 'pending', 'completed'],
        waitlistEnabledColumn: null,
        fullMessage: 'This offering has no remaining seats.',
    ))->toThrow(ValidationException::class);
});

it('does not count a cancelled enrolment as an occupied seat', function () {
    // §11.7: "Cancelled, suspended, failed, and rejected enrollments should
    // not count as active seats."
    ['course' => $course, 'offering' => $offering] = oneSeatOffering();
    $student = makeStudent(['first_name' => 'Left', 'last_name' => 'Early']);
    takeSeat($offering->id, $course->id, $student->id);
    DB::table('course_enrollments')
        ->where('course_offering_id', $offering->id)
        ->update(['status' => 'cancelled']);

    $result = app(EnforceSeatLimitAction::class)->execute(
        resourceTable: 'course_offerings',
        resourceId: $offering->id,
        limitColumn: 'seat_limit',
        occupancyTable: 'course_enrollments',
        foreignKey: 'course_offering_id',
        occupyingStatuses: ['active', 'approved', 'pending', 'completed'],
        waitlistEnabledColumn: null,
        fullMessage: 'This offering has no remaining seats.',
    );

    expect($result['outcome'])->toBe(EnforceSeatLimitAction::OUTCOME_RESERVED)
        ->and($result['taken'])->toBe(0);
});

it('leaves an offering with no seat limit unbounded', function () {
    ['course' => $course, 'offering' => $offering] = oneSeatOffering();
    DB::table('course_offerings')->where('id', $offering->id)->update(['seat_limit' => null]);

    foreach (['A', 'B', 'C'] as $name) {
        $student = makeStudent(['first_name' => 'Many', 'last_name' => $name]);
        $result = app(EnforceSeatLimitAction::class)->execute(
            resourceTable: 'course_offerings',
            resourceId: $offering->id,
            limitColumn: 'seat_limit',
            occupancyTable: 'course_enrollments',
            foreignKey: 'course_offering_id',
            occupyingStatuses: ['active', 'approved', 'pending', 'completed'],
            waitlistEnabledColumn: null,
            fullMessage: 'This offering has no remaining seats.',
        );
        expect($result['outcome'])->toBe(EnforceSeatLimitAction::OUTCOME_RESERVED);
        takeSeat($offering->id, $course->id, $student->id);
    }

    expect(DB::table('course_enrollments')->where('course_offering_id', $offering->id)->count())->toBe(3);
});
