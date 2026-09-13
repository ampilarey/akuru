<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Offerings\Actions\BackfillCourseOfferingsAction;
use App\Domains\Offerings\Enums\DeliveryMode;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * ROADMAP §3.4's missing half.
 *
 * The split says every existing course becomes a course **plus one
 * auto-created offering**, with `course_enrollments` repointed. The 1B audit
 * recorded on 2026-08-27 that the backfill was never written — justified then
 * by ADR-021 (no real data) — and that it **"becomes mandatory before first
 * real use"**, the same trigger that reactivates rule 9 in full.
 *
 * This is the **backfill** step of rule 9's three deploys and only that: it
 * writes the offering side and leaves every read where it is. Nothing here
 * changes what a screen shows today, which is why no test asserts a changed
 * page — asserting one would mean the slice had done something it must not.
 */
function backfillStudentId(): int
{
    return (int) makeRegistrationStudent()->id;
}

function backfillCourse(array $attributes = []): Course
{
    return Course::factory()->create($attributes + [
        'workflow_status' => 'published',
        'status' => 'open',
    ]);
}

it('gives a course with no offering exactly one', function () {
    $course = backfillCourse();
    expect(CourseOffering::query()->where('course_id', $course->id)->count())->toBe(0);

    $report = app(BackfillCourseOfferingsAction::class)->execute();

    expect($report['offerings_created'])->toBe(1)
        ->and(CourseOffering::query()->where('course_id', $course->id)->count())->toBe(1);
});

it('creates the mode the live read path actually looks for', function () {
    // §3.4's parenthetical says "face-to-face or as appropriate". The
    // appropriate mode is self-learning, because that is what
    // DefaultSelfLearningOfferingAction — which checkout uses to find an
    // offering and its price — filters on. A face-to-face backfill would
    // satisfy the sentence and leave checkout still finding nothing.
    $course = backfillCourse();

    app(BackfillCourseOfferingsAction::class)->execute();

    expect(CourseOffering::query()->where('course_id', $course->id)->value('delivery_mode'))
        ->toBe(DeliveryMode::SelfLearning);
});

it('repoints a legacy enrollment that predates the split', function () {
    $course = backfillCourse();
    $enrollment = CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => backfillStudentId(),
        'status' => 'active',
        'payment_status' => 'not_required',
        'enrollment_type' => 'free',
    ]);

    expect($enrollment->course_offering_id)->toBeNull();

    $report = app(BackfillCourseOfferingsAction::class)->execute();
    $offeringId = CourseOffering::query()->where('course_id', $course->id)->value('id');

    expect($report['enrollments_repointed'])->toBe(1)
        ->and($enrollment->fresh()->course_offering_id)->toBe($offeringId);
});

it('moves the §3.4 columns onto the offering', function () {
    // The reason the public site still reads courses.seats: the lazy creator
    // makes an offering and copies nothing onto it.
    $course = backfillCourse([
        'seats' => 30,
        'registration_fee_amount' => 250,
        'start_date' => '2026-01-05',
        'end_date' => '2026-03-30',
    ]);

    app(BackfillCourseOfferingsAction::class)->execute();

    $offering = CourseOffering::query()->where('course_id', $course->id)->firstOrFail();

    expect($offering->seat_limit)->toBe(30)
        ->and((float) $offering->price_override)->toBe(250.0)
        ->and($offering->starts_at)->not->toBeNull()
        ->and($offering->ends_at)->not->toBeNull();
});

it('runs twice without making a second offering or a second repoint', function () {
    // Idempotency is what makes this safe to re-run on a restored dump, which
    // is how rule 9 says to rehearse it.
    $course = backfillCourse(['seats' => 12]);
    CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => backfillStudentId(),
        'status' => 'active',
        'payment_status' => 'not_required',
        'enrollment_type' => 'free',
    ]);

    $first = app(BackfillCourseOfferingsAction::class)->execute();
    $second = app(BackfillCourseOfferingsAction::class)->execute();

    expect($first['offerings_created'])->toBe(1)
        ->and($second['offerings_created'])->toBe(0)
        ->and($second['enrollments_repointed'])->toBe(0)
        ->and($second['columns_filled'])->toBe(0)
        ->and(CourseOffering::query()->where('course_id', $course->id)->count())->toBe(1);
});

it('never overwrites a number somebody has corrected on the offering', function () {
    // The failure that would be silent and expensive: an admin sets the real
    // seat limit on the offering, a later re-run reverts it to the stale
    // course column, and a class is oversold.
    $course = backfillCourse(['seats' => 30]);

    app(BackfillCourseOfferingsAction::class)->execute();

    $offering = CourseOffering::query()->where('course_id', $course->id)->firstOrFail();
    $offering->forceFill(['seat_limit' => 8])->save();

    app(BackfillCourseOfferingsAction::class)->execute();

    expect($offering->fresh()->seat_limit)->toBe(8);
});

it('leaves an enrollment that already points at an offering alone', function () {
    $course = backfillCourse();
    $other = CourseOffering::query()->create([
        'course_id' => $course->id,
        'title' => 'Evening batch',
        'slug' => 'evening',
        'delivery_mode' => DeliveryMode::FaceToFace,
        'status' => 'open',
        'pin_mode' => 'latest',
    ]);

    $enrollment = CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'course_offering_id' => $other->id,
        'student_id' => backfillStudentId(),
        'status' => 'active',
        'payment_status' => 'not_required',
        'enrollment_type' => 'free',
    ]);

    app(BackfillCourseOfferingsAction::class)->execute();

    expect($enrollment->fresh()->course_offering_id)->toBe($other->id);
});

it('passes its own gate once the backfill has run, and fails before', function () {
    backfillCourse();
    CourseEnrollment::query()->create([
        'course_id' => Course::query()->value('id'),
        'student_id' => backfillStudentId(),
        'status' => 'active',
        'payment_status' => 'not_required',
        'enrollment_type' => 'free',
    ]);

    $this->artisan('offerings:verify-backfill')->assertExitCode(1);

    $this->artisan('offerings:verify-backfill --backfill')
        ->expectsOutputToContain('offerings:verify-backfill OK')
        ->assertExitCode(0);
});
