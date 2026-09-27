<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Models\Payment;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The office's enrolment and payments lists (docs/ADMIN_PANEL.md; C9 slice
 * 4, STATUS §5jf): Inertia pages with every string keyed EN/DV/AR, the same
 * filters and CSVs the Blade screens had, and the refund form only where
 * money can still come back and only for whoever may refund.
 */
function listsOffice(array $permissions = []): User
{
    $user = User::factory()->create(['name' => 'Office Admin']);
    $user->assignRole(Role::findOrCreate('admin', 'web'));
    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user->fresh();
}

function listsAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

function listsEnrolment(string $first, string $status, string $paymentStatus, ?Course $course = null): CourseEnrollment
{
    $user = User::factory()->create(['name' => $first.' Parent']);
    $student = makeCourseStudent(['user_id' => $user->id, 'first_name' => $first, 'last_name' => 'Ibrahim', 'dob' => now()->subYears(12)]);

    return CourseEnrollment::query()->create([
        'unified_student_id' => $student->id,
        'course_id' => ($course ?? Course::factory()->create())->id,
        'status' => $status,
        'payment_status' => $paymentStatus,
        'created_by_user_id' => $user->id,
    ]);
}

it('lists the enrolments newest first with the filters, the keyed labels and the CSV carrying the filters', function () {
    $office = listsOffice();
    $tajweed = Course::factory()->create(['title' => 'Tajweed Basics']);
    $fiqh = Course::factory()->create(['title' => 'Fiqh One']);
    $older = listsEnrolment('Aishath', 'active', 'confirmed', $tajweed);
    $newer = listsEnrolment('Hassan', 'pending', 'required', $fiqh);

    listsAs($office)->get(route('admin.enrollments.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admissions/Enrollments')
            ->where('total', 2)
            ->where('enrollments.0.id', $newer->id)->where('enrollments.0.student', 'Hassan Ibrahim')->where('enrollments.0.course', 'Fiqh One')
            ->where('enrollments.0.status', 'pending')->where('enrollments.0.payment_status', 'required')
            ->where('enrollments.1.id', $older->id)
            ->where('courses', fn ($courses) => collect($courses)->pluck('title')->all() === ['Fiqh One', 'Tajweed Basics'])
            ->where('statuses.0', 'pending')->where('payment_statuses.1', 'required')
            ->where('filters.status', '')
            ->where('t.enrolments_title', 'Enrollments')->where('t.enrolments_status_suspended', 'Suspended'));

    // Each filter narrows; an unknown status is ignored rather than trusted.
    listsAs($office)->get(route('admin.enrollments.index', ['status' => 'active']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('enrollments.0.id', $older->id)->where('filters.status', 'active'));
    listsAs($office)->get(route('admin.enrollments.index', ['course_id' => $fiqh->id]))
        ->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('enrollments.0.id', $newer->id));
    listsAs($office)->get(route('admin.enrollments.index', ['payment_status' => 'confirmed']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('enrollments.0.id', $older->id));
    listsAs($office)->get(route('admin.enrollments.index', ['search' => 'Aishath']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('enrollments.0.student', 'Aishath Ibrahim'));
    listsAs($office)->get(route('admin.enrollments.index', ['status' => "' OR 1=1"]))
        ->assertInertia(fn (Assert $page) => $page->where('total', 2));

    // The CSV is the filtered list.
    $csv = listsAs($office)->get(route('admin.enrollments.export', ['status' => 'active']))->assertOk()->streamedContent();
    expect($csv)->toContain('ID,Course,"Student Name"')->toContain('Aishath Ibrahim')->not->toContain('Hassan Ibrahim');

    // Dhivehi and Arabic carry every key the pages read.
    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['enrolments_title', 'enrolments_status_pending', 'enrolments_pay_required', 'enrolments_view', 'payments_title', 'payments_refund_confirm', 'payments_status_refunded'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }

    // The supervisor no longer grants places (ADR-040 slice 2); a teacher never did.
    $supervisor = User::factory()->create();
    $supervisor->assignRole(Role::findOrCreate('supervisor', 'web'));
    listsAs($supervisor)->get(route('admin.enrollments.index'))->assertForbidden();
});

it('lists the payments with the status filter and offers the refund form only where money can come back, to whoever may refund', function () {
    // The educational admin holds `payments.refund` by the role matrix
    // (RoleGrants); the dean does not, so the dean sees no refund form.
    $office = listsOffice();
    $dean = User::factory()->create(['name' => 'The Dean']);
    $dean->assignRole(Role::findOrCreate('headmaster', 'web'));
    $payer = User::factory()->create(['name' => 'Mariyam Payer']);
    $enrolment = listsEnrolment('Zaid', 'active', 'confirmed');
    $confirmed = Payment::query()->create([
        'user_id' => $payer->id, 'unified_student_id' => $enrolment->unified_student_id, 'amount' => 250, 'currency' => 'MVR', 'status' => 'confirmed',
        'provider' => 'manual', 'merchant_reference' => 'AKURU-CONFIRMED-1', 'payable_type' => 'course_enrollment', 'payable_id' => $enrolment->id,
    ]);
    $failed = Payment::query()->create([
        'user_id' => $payer->id, 'amount' => 90, 'currency' => 'MVR', 'status' => 'failed', 'provider' => 'bml', 'merchant_reference' => 'AKURU-FAILED-2',
    ]);

    listsAs($dean->fresh())->get(route('admin.enrollments.payments'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admissions/Payments')
            ->where('total', 2)
            ->where('payments.0.id', $failed->id)->where('payments.0.status', 'failed')->where('payments.0.refundable', null)
            ->where('payments.1.id', $confirmed->id)->where('payments.1.payer', 'Mariyam Payer')->where('payments.1.student', 'Zaid Ibrahim')
            ->where('payments.1.amount', '250.00')->where('payments.1.currency', 'MVR')->where('payments.1.reference', 'AKURU-CONFIRMED-1')
            ->where('payments.1.refundable', '250.00')->where('payments.1.refunded', null)
            // The dean, without the permission, sees no refund form whatever the balance.
            ->where('can_refund', false)
            ->where('statuses', ['confirmed', 'pending', 'failed', 'expired', 'refunded'])
            ->where('t.payments_title', 'Payments'));

    expect($office->can('payments.refund'))->toBeTrue();
    listsAs($office)->get(route('admin.enrollments.payments', ['status' => 'confirmed']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('payments.0.id', $confirmed->id)->where('can_refund', true)->where('filters.status', 'confirmed'));
    listsAs($office)->get(route('admin.enrollments.payments', ['search' => 'FAILED-2']))
        ->assertInertia(fn (Assert $page) => $page->where('total', 1)->where('payments.0.id', $failed->id));

    // The CSV is the filtered list.
    $csv = listsAs($office)->get(route('admin.enrollments.payments.export', ['status' => 'failed']))->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
    expect($csv)->toContain('id,reference,payer')->toContain('AKURU-FAILED-2')->not->toContain('AKURU-CONFIRMED-1');
});
