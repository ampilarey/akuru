<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Activating an enrolment grants somebody a place on a course.
 *
 * `AdminRouteNamesTest` proves the routes are *registered*; nothing tested what
 * they do or who may do it. These are the behaviour and authorization tests.
 *
 * SMS stays off the wire because `phpunit.xml` sets `SMS_LIVE=false` — worth
 * saying out loud, since both endpoints message a family on success and a test
 * suite that texted real people would be a worse defect than the one being
 * covered.
 */
function pendingEnrollment(): CourseEnrollment
{
    $user = User::factory()->create();
    $student = makeCourseStudent([
        'user_id' => $user->id,
        'first_name' => 'Aishath',
        'last_name' => 'Ibrahim',
        'dob' => now()->subYears(12),
    ]);

    return CourseEnrollment::create([
        'unified_student_id' => $student->id,
        'course_id' => Course::factory()->create(['registration_fee_amount' => 500])->id,
        'status' => 'pending',
        'payment_status' => 'pending',
        'created_by_user_id' => $user->id,
    ]);
}

function enrollmentStaff(string $role): User
{
    $user = User::factory()->create();
    Role::findOrCreate($role, 'web');
    $user->assignRole($role);

    return $user;
}

it('refuses a signed-in account with no role', function () {
    $enrollment = pendingEnrollment();

    $this->withoutLocalizationMiddleware()
        ->actingAs(User::factory()->create())
        ->patch(route('admin.enrollments.activate', $enrollment))
        ->assertForbidden();

    expect($enrollment->refresh()->status)->toBe('pending');
});

it('refuses a parent activating their own childs enrolment', function () {
    $enrollment = pendingEnrollment();

    // The obvious abuse: approve your own place on a paid course.
    $this->withoutLocalizationMiddleware()
        ->actingAs(enrollmentStaff('parent'))
        ->patch(route('admin.enrollments.activate', $enrollment))
        ->assertForbidden();

    expect($enrollment->refresh()->status)->toBe('pending');
});

it('refuses a teacher', function () {
    $enrollment = pendingEnrollment();

    $this->withoutLocalizationMiddleware()
        ->actingAs(enrollmentStaff('teacher'))
        ->patch(route('admin.enrollments.activate', $enrollment))
        ->assertForbidden();

    expect($enrollment->refresh()->status)->toBe('pending');
});

it('refuses an anonymous visitor', function () {
    $enrollment = pendingEnrollment();

    $this->withoutLocalizationMiddleware()
        ->patch(route('admin.enrollments.activate', $enrollment))
        ->assertRedirect();

    expect($enrollment->refresh()->status)->toBe('pending');
});

it('activates for each role that runs admissions', function () {
    // The educational admin's, and the dean's (the owner: "school fees,
    // principal can see"); the system admin passes every gate.
    foreach (['super_admin', 'admin', 'headmaster'] as $role) {
        $enrollment = pendingEnrollment();

        $this->withoutLocalizationMiddleware()
            ->actingAs(enrollmentStaff($role))
            ->patch(route('admin.enrollments.activate', $enrollment))
            ->assertRedirect();

        expect($enrollment->refresh()->status)->toBe('active');
    }
});

it('refuses a supervisor, who used to be able to grant a place on a paid course', function () {
    // KNOWN_ISSUES 12, decided with the educational admin's permission set
    // (ADR-040 slice 2): admissions are administration, not supervision.
    $enrollment = pendingEnrollment();

    $this->withoutLocalizationMiddleware()
        ->actingAs(enrollmentStaff('supervisor'))
        ->patch(route('admin.enrollments.activate', $enrollment))
        ->assertForbidden();
    $this->withoutLocalizationMiddleware()
        ->actingAs(enrollmentStaff('supervisor'))
        ->get(route('admin.enrollments.index'))
        ->assertForbidden();

    expect($enrollment->refresh()->status)->toBe('pending');
});

it('stamps enrolled_at when it activates', function () {
    $enrollment = pendingEnrollment();

    $this->withoutLocalizationMiddleware()
        ->actingAs(enrollmentStaff('admin'))
        ->patch(route('admin.enrollments.activate', $enrollment))
        ->assertRedirect();

    expect($enrollment->refresh()->enrolled_at)->not->toBeNull();
});

it('keeps the original enrolment date on a re-activation', function () {
    $enrollment = pendingEnrollment();
    $enrollment->update(['enrolled_at' => now()->subMonth()]);
    $original = $enrollment->refresh()->enrolled_at->toDateString();

    $this->withoutLocalizationMiddleware()
        ->actingAs(enrollmentStaff('admin'))
        ->patch(route('admin.enrollments.activate', $enrollment))
        ->assertRedirect();

    // `enrolled_at ?? now()` — the date somebody actually joined is not
    // rewritten by an administrative re-run.
    expect($enrollment->refresh()->enrolled_at->toDateString())->toBe($original);
});

it('rejects an enrolment', function () {
    $enrollment = pendingEnrollment();

    $this->withoutLocalizationMiddleware()
        ->actingAs(enrollmentStaff('admin'))
        ->patch(route('admin.enrollments.reject', $enrollment))
        ->assertRedirect();

    expect($enrollment->refresh()->status)->toBe('rejected');
});

it('refuses a parent rejecting an enrolment', function () {
    $enrollment = pendingEnrollment();

    $this->withoutLocalizationMiddleware()
        ->actingAs(enrollmentStaff('parent'))
        ->patch(route('admin.enrollments.reject', $enrollment))
        ->assertForbidden();

    expect($enrollment->refresh()->status)->toBe('pending');
});

it('activates a place that has not been paid for', function () {
    $enrollment = pendingEnrollment();

    $this->withoutLocalizationMiddleware()
        ->actingAs(enrollmentStaff('admin'))
        ->patch(route('admin.enrollments.activate', $enrollment))
        ->assertRedirect();

    // Recording the behaviour rather than blessing it: this is a **manual
    // override**, and it does not contradict money rule 12 — that rule says
    // automatic access follows the BML webhook and never the return URL. A
    // named member of staff deciding to admit an unpaid pupil is a different
    // thing from the system admitting them by accident.
    //
    // This endpoint is guarded by role — the educational admin's and the
    // dean's since ADR-040 slice 2, a supervisor no longer (KNOWN_ISSUES 12).
    // The money endpoints next door (refund, record-payment) additionally
    // require `can:payments.refund` / `can:payments.record`, which the
    // educational admin's set holds.
    expect($enrollment->refresh()->status)->toBe('active')
        ->and($enrollment->payment_status)->toBe('pending');
});
