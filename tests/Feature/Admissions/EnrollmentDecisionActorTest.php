<?php

use App\Domains\Courses\Actions\ActivateEnrollmentAction;
use App\Domains\Courses\Actions\RecordEnrollmentDecisionAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Who decided an enrolment, and when (admin-panel audit finding 6,
 * KNOWN_ISSUES "Enrolment decisions record no actor", STATUS §5ih).
 * Activate, reject, suspend, reinstate and the access window wrote the
 * status and nothing about who did it; `reject` wrote it from the
 * controller. Each now goes through an Action that stamps the actor; the
 * webhook's activation stamps nobody, because the system decided.
 */
function decidableEnrollment(): CourseEnrollment
{
    $registrant = User::factory()->create();
    $student = makeCourseStudent(['user_id' => $registrant->id, 'first_name' => 'Mariyam', 'last_name' => 'Waheed']);

    return CourseEnrollment::create([
        'unified_student_id' => $student->id,
        'course_id' => Course::factory()->create(['registration_fee_amount' => 500])->id,
        'status' => 'pending',
        'payment_status' => 'pending',
        'created_by_user_id' => $registrant->id,
    ]);
}

function decidingAdmin(): User
{
    $user = User::factory()->create(['name' => 'Office Admin']);
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    return $user;
}

function decideAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

it('records who activated, rejected, suspended, reinstated and set the window, and when', function () {
    $admin = decidingAdmin();
    $enrollment = decidableEnrollment();
    expect($enrollment->decided_at)->toBeNull()->and($enrollment->decision)->toBeNull();

    decideAs($admin)->patch(route('admin.enrollments.activate', $enrollment))->assertRedirect();
    $enrollment->refresh();
    expect($enrollment->status)->toBe('active')
        ->and($enrollment->decision)->toBe(RecordEnrollmentDecisionAction::ACTIVATED)
        ->and((int) $enrollment->decided_by_user_id)->toBe($admin->id)
        ->and($enrollment->decided_at)->not->toBeNull();

    decideAs($admin)->patch(route('admin.enrollments.suspend', $enrollment))->assertRedirect();
    expect($enrollment->refresh()->decision)->toBe(RecordEnrollmentDecisionAction::SUSPENDED);

    decideAs($admin)->patch(route('admin.enrollments.reinstate', $enrollment))->assertRedirect();
    expect($enrollment->refresh()->decision)->toBe(RecordEnrollmentDecisionAction::REINSTATED)->and($enrollment->status)->toBe('active');

    $other = decidingAdmin();
    decideAs($other)->patch(route('admin.enrollments.access-window', $enrollment), ['access_starts_at' => '2026-10-01T08:00', 'access_ends_at' => ''])->assertRedirect();
    $enrollment->refresh();
    expect($enrollment->decision)->toBe(RecordEnrollmentDecisionAction::ACCESS_WINDOW)
        ->and((int) $enrollment->decided_by_user_id)->toBe($other->id)
        ->and($enrollment->access_starts_at?->toDateString())->toBe('2026-10-01');

    decideAs($admin)->patch(route('admin.enrollments.reject', $enrollment))->assertRedirect();
    $enrollment->refresh();
    expect($enrollment->status)->toBe('rejected')
        ->and($enrollment->decision)->toBe(RecordEnrollmentDecisionAction::REJECTED)
        ->and((int) $enrollment->decided_by_user_id)->toBe($admin->id);
});

it('stamps nobody when the system decides, and shows the stamp on the page and in the CSV', function () {
    $admin = decidingAdmin();
    $webhookActivated = decidableEnrollment();
    app(ActivateEnrollmentAction::class)->execute($webhookActivated);
    expect($webhookActivated->refresh()->status)->toBe('active')->and($webhookActivated->decided_at)->toBeNull();

    decideAs($admin)->get(route('admin.enrollments.show', $webhookActivated))->assertOk()
        ->assertSee('no decision recorded');

    $decided = decidableEnrollment();
    decideAs($admin)->patch(route('admin.enrollments.activate', $decided));
    decideAs($admin)->get(route('admin.enrollments.show', $decided))->assertOk()
        ->assertSee('Activated by Office Admin on');

    $csv = decideAs($admin)->get(route('admin.enrollments.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('"Last Decision","Decided By","Decided At"')
        ->toContain('activated,"Office Admin",');
});
