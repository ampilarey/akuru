<?php

use App\Domains\HR\Actions\SaveCpdRecordAction;
use App\Domains\HR\Actions\SummarizeCpdHoursAction;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * S5.5 asked for CPD hours per staff member; the screen listed records and
 * left the adding-up to the reader (S5 audit D5, STATUS §5ff).
 */
it('adds up CPD hours per staff member for this year and all time, on the HR screen, the CSV and the portal', function () {
    makeYear(['is_current' => true, 'status' => 'active', 'start_date' => '2026-06-01', 'end_date' => '2027-05-31']);
    $user = User::factory()->create();
    $staff = makeStaffProfile(['user_id' => $user->id, 'first_name' => 'Hawwa', 'last_name' => 'Ali']);
    $other = makeStaffProfile(['first_name' => 'Ibrahim', 'last_name' => 'Naseer']);

    $save = app(SaveCpdRecordAction::class);
    $save->execute(['staff_profile_id' => $staff->id, 'title' => 'Phonics', 'hours' => 3, 'date' => '2026-09-01']);
    $save->execute(['staff_profile_id' => $staff->id, 'title' => 'Safeguarding', 'hours' => 1.5, 'date' => '2026-07-15']);
    $save->execute(['staff_profile_id' => $staff->id, 'title' => 'Old course', 'hours' => 10, 'date' => '2025-03-01']);
    $save->execute(['staff_profile_id' => $other->id, 'title' => 'First aid', 'hours' => 2, 'date' => '2026-08-01']);

    $summary = app(SummarizeCpdHoursAction::class)->execute()->keyBy('staff_profile_id');
    expect($summary[$staff->id]['hours_this_year'])->toBe(4.5)
        ->and($summary[$staff->id]['records_this_year'])->toBe(2)
        ->and($summary[$staff->id]['hours_total'])->toBe(14.5)
        ->and($summary[$staff->id]['records_total'])->toBe(3)
        ->and($summary[$other->id]['hours_this_year'])->toBe(2.0);

    $admin = actingPeopleAdmin(['hr.manage']);
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('hr.cpd.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('HR/Performance/Cpd')
            ->where('summary.0.staff_name', 'Hawwa Ali')
            ->where('summary.0.hours_this_year', 4.5));

    $csv = $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('hr.cpd.summary.export'));
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('hours_this_year')
        ->and($csv->streamedContent())->toContain('"Hawwa Ali",4.5,2,14.5,3');

    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->get(route('portal.appraisals'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('cpdSummary.hours_this_year', 4.5)
            ->where('cpdSummary.hours_total', 14.5));
});
