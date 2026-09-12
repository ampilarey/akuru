<?php

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Offerings\Actions\SaveCourseOfferingAction;
use App\Domains\Offerings\Actions\TransitionOfferingStatusAction;
use App\Domains\Offerings\Enums\OfferingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §11.4 "Offering Status Workflow":
 *
 *   > Draft → Open → In Progress → Completed → Cancelled → Archived
 *   >
 *   > Invalid transitions must be rejected.
 *
 * Two things were wrong.
 *
 * The enum carried **four** of §11.4's six states — Draft, Open, Closed,
 * Archived — so In Progress, Completed and Cancelled did not exist, and
 * `Closed` was invented. Collapsing Completed and Cancelled into one value
 * makes a finished cohort indistinguishable from an abandoned one.
 *
 * And nothing rejected anything: `SaveCourseOfferingAction` stored whatever
 * `status` arrived, so an offering could go Archived → Draft or Completed →
 * Open in a single request.
 */
uses(RefreshDatabase::class);

function statusOffering(OfferingStatus $status = OfferingStatus::Draft)
{
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Status course '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);

    $offering = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Batch '.uniqueFixtureSuffix(),
        'delivery_mode' => 'face_to_face',
    ]);

    // Set the starting state directly; the transition rules are what the
    // tests below are for.
    $offering->forceFill(['status' => $status])->save();

    return $offering->refresh();
}

it('carries every state SPEC §11.4 names', function () {
    $values = array_map(fn (OfferingStatus $s) => $s->value, OfferingStatus::cases());

    expect($values)->toContain('draft')
        ->toContain('open')
        ->toContain('in_progress')
        ->toContain('completed')
        ->toContain('cancelled')
        ->toContain('archived');
});

it('keeps the legacy closed value rather than dropping it (rule 9)', function () {
    // No code writes it, but an enum case that disappears turns any row
    // holding it into a cast error on read, and this repo cannot inspect a
    // deployment's data.
    expect(OfferingStatus::tryFrom('closed'))->toBe(OfferingStatus::Closed);
});

it('walks the happy path §11.4 draws', function () {
    $offering = statusOffering(OfferingStatus::Draft);
    $transition = app(TransitionOfferingStatusAction::class);

    expect($transition->execute($offering, OfferingStatus::Open)->status)->toBe(OfferingStatus::Open)
        ->and($transition->execute($offering->refresh(), OfferingStatus::InProgress)->status)->toBe(OfferingStatus::InProgress)
        ->and($transition->execute($offering->refresh(), OfferingStatus::Completed)->status)->toBe(OfferingStatus::Completed)
        ->and($transition->execute($offering->refresh(), OfferingStatus::Archived)->status)->toBe(OfferingStatus::Archived);
});

it('refuses to reopen a completed cohort', function () {
    // The case that matters: a finished offering being put back on sale by a
    // mistyped form field, with nothing to say so.
    $offering = statusOffering(OfferingStatus::Completed);

    expect(fn () => app(TransitionOfferingStatusAction::class)->execute($offering, OfferingStatus::Open))
        ->toThrow(ValidationException::class);
});

it('refuses to bring an archived offering back to draft', function () {
    $offering = statusOffering(OfferingStatus::Archived);

    expect(fn () => app(TransitionOfferingStatusAction::class)->execute($offering, OfferingStatus::Draft))
        ->toThrow(ValidationException::class);
});

it('refuses to skip straight from draft to completed', function () {
    $offering = statusOffering(OfferingStatus::Draft);

    expect(fn () => app(TransitionOfferingStatusAction::class)->execute($offering, OfferingStatus::Completed))
        ->toThrow(ValidationException::class);
});

it('allows cancelling from any live state, not only from completed', function () {
    // §11.4's arrows read as the happy path; its prose describes Cancelled as
    // keeping "historical/admin records", which only makes sense for a cohort
    // abandoned part-way. Forcing an admin to mark a cancelled batch
    // "completed" first would put a false record in the history.
    $transition = app(TransitionOfferingStatusAction::class);

    expect($transition->execute(statusOffering(OfferingStatus::Draft), OfferingStatus::Cancelled)->status)
        ->toBe(OfferingStatus::Cancelled)
        ->and($transition->execute(statusOffering(OfferingStatus::Open), OfferingStatus::Cancelled)->status)
        ->toBe(OfferingStatus::Cancelled)
        ->and($transition->execute(statusOffering(OfferingStatus::InProgress), OfferingStatus::Cancelled)->status)
        ->toBe(OfferingStatus::Cancelled);
});

it('treats staying put as a no-op, not an invalid transition', function () {
    // A retry or a double-submitted form must not read as an error.
    $offering = statusOffering(OfferingStatus::Archived);

    expect(app(TransitionOfferingStatusAction::class)->execute($offering, OfferingStatus::Archived)->status)
        ->toBe(OfferingStatus::Archived);
});

it('lets a legacy closed offering be resolved into the real vocabulary', function () {
    $offering = statusOffering(OfferingStatus::Closed);

    expect(app(TransitionOfferingStatusAction::class)->execute($offering, OfferingStatus::Completed)->status)
        ->toBe(OfferingStatus::Completed);
});

it('rejects an invalid transition arriving through an ordinary edit', function () {
    // The save path is where this would actually happen: an edit form posts
    // the whole offering back, status included.
    $offering = statusOffering(OfferingStatus::Completed);

    expect(fn () => app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $offering->course_id,
        'title' => 'Renamed batch',
        'delivery_mode' => 'face_to_face',
        'status' => 'open',
    ], $offering))->toThrow(ValidationException::class);
});

it('still lets an ordinary edit through when the status does not change', function () {
    $offering = statusOffering(OfferingStatus::Open);

    $updated = app(SaveCourseOfferingAction::class)->execute([
        'course_id' => $offering->course_id,
        'title' => 'Renamed batch',
        'delivery_mode' => 'face_to_face',
        'status' => 'open',
    ], $offering);

    expect($updated->title)->toBe('Renamed batch')
        ->and($updated->status)->toBe(OfferingStatus::Open);
});

it('answers who accepts enrolment and who is visible, per §11.4', function () {
    expect(OfferingStatus::Open->acceptsEnrolment())->toBeTrue()
        ->and(OfferingStatus::Draft->acceptsEnrolment())->toBeFalse()
        ->and(OfferingStatus::Archived->acceptsEnrolment())->toBeFalse()
        ->and(OfferingStatus::Completed->acceptsEnrolment())->toBeFalse()
        ->and(OfferingStatus::Draft->isVisibleToStudents())->toBeFalse()
        ->and(OfferingStatus::Open->isVisibleToStudents())->toBeTrue();
});
