<?php

use App\Domains\Courses\Actions\DeleteCourseAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §29: "Never hard-delete a course … if it has: Enrollments … Payment
 * records. Historical student data must remain intact."
 *
 * `admin.courses.destroy` called `$course->delete()` on a model with no soft
 * deletes, and the foreign keys made that far worse than a missing row —
 * `course_enrollments.course_id` and `payment_items.course_id` both CASCADE, so
 * the roster and its payment line items went with it. Rule 12 says ledgers are
 * append-only; a cascade is the quietest delete there is.
 *
 * The first test below is the one that would have caught it.
 */
uses(RefreshDatabase::class);

function deletableCourse(string $title = 'Deletable'): Course
{
    return Course::query()->create([
        'course_category_id' => DB::table('course_categories')->insertGetId([
            'name' => 'Delete test', 'slug' => 'delete-test-'.Str::random(6), 'order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'title' => $title,
        'slug' => Str::slug($title).'-'.Str::random(6),
        'short_desc' => 'For the delete test.',
        'body' => 'Body.',
        'cover_image' => '',
        'workflow_status' => 'draft',
        'course_type' => 'general',
        'status' => 'open',
    ]);
}

it('keeps the roster when a course with enrolments is deleted', function () {
    $course = deletableCourse('Has a roster');
    $enrollment = CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => makeRegistrationStudent()->id,
        'status' => 'active',
        'payment_status' => 'confirmed',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => 0,
    ]);

    $result = app(DeleteCourseAction::class)->execute($course);

    // Archived, not removed — and the enrolment is still there, which is the
    // whole point: `course_enrollments.course_id` cascades.
    expect($result['soft'])->toBeTrue()
        ->and($result['blocked_by'])->toHaveKey('course_enrollments')
        ->and(Course::withTrashed()->whereKey($course->id)->exists())->toBeTrue()
        ->and(Course::query()->whereKey($course->id)->exists())->toBeFalse()
        ->and(CourseEnrollment::query()->whereKey($enrollment->id)->exists())->toBeTrue();
});

it('keeps payment line items when a course is deleted', function () {
    $course = deletableCourse('Has been paid for');

    // `payment_items.payment_id` is NOT NULL — a line item always belongs to a
    // payment, which is exactly why cascading it away is a ledger delete.
    $paymentId = DB::table('payments')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'user_id' => \App\Domains\Identity\Models\User::factory()->create()->id,
        'course_id' => $course->id,
        'amount' => 500,
        'currency' => 'MVR',
        // `status` is a DB enum; 'paid' is not one of its values —
        // 'confirmed' is what the webhook sets (rule 12).
        'status' => 'confirmed',
        'provider' => 'bml',
        'merchant_reference' => 'REF-'.Str::random(10),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Every payment_items column is NOT NULL: a line item is payment +
    // enrolment + course + amount, which is precisely why a course cascade
    // taking it away is a ledger delete rather than a tidy-up.
    $enrollment = CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => makeRegistrationStudent()->id,
        'status' => 'active',
        'payment_status' => 'confirmed',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => 0,
    ]);

    DB::table('payment_items')->insert([
        'payment_id' => $paymentId,
        'enrollment_id' => $enrollment->id,
        'course_id' => $course->id,
        'amount' => 500,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = app(DeleteCourseAction::class)->execute($course);

    // Rule 12: money rows are append-only. A cascade that removes them is a
    // delete by another name.
    expect($result['soft'])->toBeTrue()
        ->and($result['blocked_by'])->toHaveKey('payment_items')
        ->and(DB::table('payment_items')->where('course_id', $course->id)->count())->toBe(1);
});

it('still removes an empty draft outright', function () {
    $course = deletableCourse('Abandoned draft');

    $result = app(DeleteCourseAction::class)->execute($course);

    // §29 permits this explicitly, and without it the table fills with drafts
    // nobody can clear.
    expect($result['soft'])->toBeFalse()
        ->and($result['blocked_by'])->toBeEmpty()
        ->and(Course::withTrashed()->whereKey($course->id)->exists())->toBeFalse();
});

it('names every dependent when asked to refuse rather than archive', function () {
    $course = deletableCourse('Busy course');
    CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => makeRegistrationStudent()->id,
        'status' => 'active',
        'payment_status' => 'pending',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => 0,
    ]);

    try {
        app(DeleteCourseAction::class)->executeStrict($course);
        expect(false)->toBeTrue('executeStrict should have thrown');
    } catch (ValidationException $e) {
        // The message has to say what is in the way, or an admin is left
        // guessing why the button did nothing.
        expect($e->errors()['course'][0])->toContain('1 enrolments')
            ->and($e->errors()['course'][0])->toContain('§29');
    }

    expect(Course::withTrashed()->whereKey($course->id)->exists())->toBeTrue();
});

it('hides an archived course from ordinary queries but keeps it findable', function () {
    $course = deletableCourse('Archived');
    CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => makeRegistrationStudent()->id,
        'status' => 'active',
        'payment_status' => 'pending',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => 0,
    ]);

    app(DeleteCourseAction::class)->execute($course);

    expect(Course::query()->count())->toBe(0)
        ->and(Course::withTrashed()->count())->toBe(1)
        ->and(Course::withTrashed()->first()->deleted_at)->not->toBeNull();
});

it('soft-deletes an enrolment rather than losing it', function () {
    $course = deletableCourse('For the enrolment');
    $enrollment = CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => makeRegistrationStudent()->id,
        'status' => 'active',
        'payment_status' => 'confirmed',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => 0,
    ]);

    $enrollment->delete();

    // §29 lists Enrollments among the soft-delete models: a student's record of
    // having taken something does not evaporate because somebody tidied up.
    expect(CourseEnrollment::query()->whereKey($enrollment->id)->exists())->toBeFalse()
        ->and(CourseEnrollment::withTrashed()->whereKey($enrollment->id)->exists())->toBeTrue();
});
