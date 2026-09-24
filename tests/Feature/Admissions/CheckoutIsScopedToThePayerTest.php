<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\People\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * `CheckoutController::start` binds `{course}` and then takes `enrollment_id`
 * and `student_id` from the request.
 *
 * `enrollment_id` was checked against the bound course and **nothing else**,
 * and the transaction does:
 *
 *     $enrollment->update(['payment_status' => 'pending', 'payment_id' => $payment->id]);
 *
 * So any signed-in visitor could pass a stranger's enrolment id on the same
 * course and **repoint that enrolment's payment at their own** — leaving
 * somebody else mid-checkout attached to a payment they do not control, and
 * stuck pending if it was abandoned. Enrolment ids are sequential integers.
 *
 * `student_id` was validated as `exists:...,id`, which says the row exists
 * and nothing whatever about whose it is. (Since Deploy 3 slice 2 it is a
 * `students.id`, and the fixtures below are students and `guardian_student`
 * links rather than `registration_students` and `student_guardians`.)
 *
 * This is the shape behind most of today's findings — **an identifier taken
 * from the request where the owning record was available** — and it is the
 * fourth place it has turned up. The set of students a person may act for is
 * the app's own rule rather than a new one: themselves and their children.
 */
function checkoutStudent(string $first, string $last, int $age): Student
{
    return Student::query()->create([
        'user_id' => null,
        'first_name' => $first,
        'last_name' => $last,
        'date_of_birth' => now()->subYears($age)->toDateString(),
        'gender' => 'female',
    ]);
}

function payerWithChild(): array
{
    $guardian = makeGuardian();
    $child = checkoutStudent('Mine', 'Child', 10);
    $child->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true]);

    return [$guardian->user->fresh(), $child];
}

function strangersEnrollment(Course $course): CourseEnrollment
{
    $stranger = checkoutStudent('Somebody', 'Else', 11);

    return CourseEnrollment::create([
        'unified_student_id' => $stranger->id,
        'course_id' => $course->id,
        'status' => 'pending',
        'payment_status' => 'required',
    ]);
}

it('refuses to touch an enrolment that is not the payer\'s', function () {
    [$payer] = payerWithChild();
    $course = Course::factory()->create(['registration_fee_amount' => 150]);
    $theirs = strangersEnrollment($course);

    $this->withoutLocalizationMiddleware()->actingAs($payer)
        ->post('/payments/course/'.$course->slug.'/start', [
            'accept_terms' => '1',
            'enrollment_id' => $theirs->id,
        ]);

    $fresh = $theirs->fresh();

    // Untouched: still required, still unattached to anybody's payment.
    expect($fresh->payment_status)->toBe('required')
        ->and($fresh->payment_id)->toBeNull();
});

it('refuses to enrol a student the payer has nothing to do with', function () {
    [$payer] = payerWithChild();
    $course = Course::factory()->create(['registration_fee_amount' => 150]);

    $stranger = checkoutStudent('Not', 'Mine', 9);

    $this->withoutLocalizationMiddleware()->actingAs($payer)
        ->post('/payments/course/'.$course->slug.'/start', [
            'accept_terms' => '1',
            'student_id' => $stranger->id,
        ])
        ->assertForbidden();

    expect(CourseEnrollment::where('unified_student_id', $stranger->id)->count())->toBe(0);
});

it('still lets a guardian check out for their own child', function () {
    // Without this the fix could be "refuse everything", which passes both
    // cases above and breaks paid enrolment for every family.
    [$payer, $child] = payerWithChild();
    $course = Course::factory()->create(['registration_fee_amount' => 150]);

    $this->withoutLocalizationMiddleware()->actingAs($payer)
        ->post('/payments/course/'.$course->slug.'/start', [
            'accept_terms' => '1',
            'student_id' => $child->id,
        ]);

    expect(CourseEnrollment::where('unified_student_id', $child->id)->where('course_id', $course->id)->count())->toBe(1);
});
