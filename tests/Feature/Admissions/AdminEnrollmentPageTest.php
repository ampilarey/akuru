<?php

use App\Domains\Courses\Actions\ReadAdminEnrollmentAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The one-enrolment page (docs/ADMIN_PANEL.md; C9 slice 5, STATUS §5jg): an
 * Inertia page with every string keyed EN/DV/AR, the decisions it may offer
 * worked out by the reader, the decision stamp composed in the reader's
 * language, and the same six writes the Blade page had.
 */
function pageOffice(): User
{
    $user = User::factory()->create(['name' => 'Office Admin']);
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    return $user->fresh();
}

function pageAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

function pageEnrolment(): CourseEnrollment
{
    $registrant = User::factory()->create(['name' => 'Aminath Registrant']);
    $student = makeCourseStudent(['user_id' => $registrant->id, 'first_name' => 'Yusuf', 'last_name' => 'Adam', 'gender' => 'male', 'national_id' => 'A123456']);

    return CourseEnrollment::query()->create([
        'unified_student_id' => $student->id,
        'course_id' => Course::factory()->create(['title' => 'Tajweed Basics', 'registration_fee_amount' => 500])->id,
        'status' => 'pending',
        'payment_status' => 'pending',
        'created_by_user_id' => $registrant->id,
    ]);
}

it('reads the enrolment, its student and the decisions it may offer, in the reader’s language', function () {
    $office = pageOffice();
    $enrolment = pageEnrolment();

    pageAs($office)->get(route('admin.enrollments.show', $enrolment))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admissions/Enrollment')
            ->where('enrollment.id', $enrolment->id)
            ->where('enrollment.course', 'Tajweed Basics')
            ->where('enrollment.status', 'pending')->where('enrollment.payment_status', 'pending')
            ->where('enrollment.registered_by', 'Aminath Registrant')
            ->where('enrollment.student.name', 'Yusuf Adam')->where('enrollment.student.gender', 'male')->where('enrollment.student.national_id', 'A123456')
            ->where('enrollment.student.guardians', [])
            ->where('enrollment.payment', null)
            ->where('enrollment.last_decision', fn ($stamp) => str_contains($stamp, 'no decision recorded'))
            ->where('enrollment.can_activate', true)->where('enrollment.can_reject', true)->where('enrollment.can_suspend', true)->where('enrollment.can_reinstate', false)
            ->where('enrollment.awaits_payment', true)
            ->where('enrollment.suggested_amount', fn ($amount) => str_starts_with((string) $amount, '500'))
            ->where('payment_methods.0.value', 'cash')
            // The educational admin holds payments.record by the role matrix.
            ->where('can_record_payment', true)
            ->where('t.enrolment_details', 'Enrollment Details')->where('t.enrolment_record', 'Record payment'));

    // Dhivehi and Arabic carry every key the page reads.
    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['enrolment_title', 'enrolment_no_decision', 'enrolment_decision_stamp', 'enrolment_suspend_confirm', 'enrolment_record_confirm', 'enrolment_flash_activated'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }

    // The supervisor no longer grants places (ADR-040 slice 2).
    $supervisor = User::factory()->create();
    $supervisor->assignRole(Role::findOrCreate('supervisor', 'web'));
    pageAs($supervisor)->get(route('admin.enrollments.show', $enrolment))->assertForbidden();
});

it('suspends, stamps the decision in the reader’s language, offers reinstate instead, and saves the access window with keyed flashes', function () {
    $office = pageOffice();
    $enrolment = pageEnrolment();

    pageAs($office)->patch(route('admin.enrollments.suspend', $enrolment))
        ->assertRedirect()->assertSessionHas('success', 'Enrollment suspended. The seat is released and their record is kept.');

    pageAs($office)->get(route('admin.enrollments.show', $enrolment))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('enrollment.status', 'suspended')
            ->where('enrollment.can_reinstate', true)->where('enrollment.can_suspend', false)->where('enrollment.can_activate', true)
            ->where('enrollment.last_decision', fn ($stamp) => str_starts_with($stamp, 'Suspended by Office Admin on')));

    // The stamp is composed in the reader's language.
    app()->setLocale('dv');
    expect(app(ReadAdminEnrollmentAction::class)->execute($enrolment->fresh())['last_decision'])->toContain('މަޑުޖެއްސި')->toContain('Office Admin');
    app()->setLocale('en');

    pageAs($office)->patch(route('admin.enrollments.access-window', $enrolment), ['access_starts_at' => '2026-10-01 08:00', 'access_ends_at' => ''])
        ->assertRedirect()->assertSessionHas('success', 'Access window saved.');
    pageAs($office)->get(route('admin.enrollments.show', $enrolment))
        ->assertInertia(fn (Assert $page) => $page->where('enrollment.access_starts_at', '2026-10-01T08:00')->where('enrollment.access_ends_at', null)
            ->where('enrollment.last_decision', fn ($stamp) => str_starts_with($stamp, 'Access window by Office Admin on')));

    // Reinstating gives the seat back: the enrolment is live again.
    pageAs($office)->patch(route('admin.enrollments.reinstate', $enrolment))->assertRedirect()->assertSessionHas('success', 'Enrollment reinstated.');
    expect($enrolment->fresh()->status)->toBe('active');
});
