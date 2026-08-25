<?php

use App\Domains\HR\Actions\AcknowledgeAppraisalAction;
use App\Domains\HR\Actions\SaveAppraisalAction;
use App\Domains\HR\Actions\SaveAppraisalCycleAction;
use App\Domains\HR\Actions\SaveCpdRecordAction;
use App\Domains\HR\Actions\SaveLessonObservationAction;
use App\Domains\HR\Enums\AppraisalStatus;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('records appraisals, observations, and CPD and lets staff acknowledge their own', function () {
    $admin = actingPeopleAdmin(['hr.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $subject = makeSubject();
    $staff = makeStaffProfile();
    $other = makeStaffProfile();

    $cycle = app(SaveAppraisalCycleAction::class)->execute([
        'name' => '2026 mid-year',
        'academic_year_id' => $year->id,
        'opens_at' => '2026-06-01',
        'closes_at' => '2026-06-30',
    ]);

    $appraisal = app(SaveAppraisalAction::class)->execute([
        'cycle_id' => $cycle->id,
        'staff_profile_id' => $staff->id,
        'appraiser_id' => $admin->id,
        'strengths' => 'Clear instruction',
        'development_areas' => 'Pacing',
        'status' => AppraisalStatus::Submitted->value,
    ]);

    app(SaveLessonObservationAction::class)->execute([
        'staff_profile_id' => $staff->id,
        'observer_id' => $admin->id,
        'date' => '2026-08-10',
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'summary' => 'Strong recitation start',
        'shared_with_staff' => true,
    ]);

    app(SaveCpdRecordAction::class)->execute([
        'staff_profile_id' => $staff->id,
        'title' => 'Tajweed workshop',
        'provider' => 'Akuru',
        'hours' => 6,
        'date' => '2026-07-01',
    ]);

    $staffUser = User::query()->findOrFail($staff->user_id);
    app(AcknowledgeAppraisalAction::class)->execute($appraisal->id, $staffUser->id, 'Noted');

    expect($appraisal->fresh()->status)->toBe(AppraisalStatus::Acknowledged);

    $otherUser = User::query()->findOrFail($other->user_id);
    expect(fn () => app(AcknowledgeAppraisalAction::class)->execute($appraisal->id, $otherUser->id))
        ->toThrow(ValidationException::class);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.appraisals.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('HR/Performance/Appraisals')->has('rows', 1));

    $this->withoutLocalizationMiddleware()
        ->actingAs($staffUser)
        ->get(route('portal.appraisals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Appraisals')
            ->has('appraisals', 1)
            ->has('observations', 1)
            ->has('cpd', 1)
        );
});
