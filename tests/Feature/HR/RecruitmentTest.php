<?php

use App\Domains\HR\Actions\SaveJobApplicationAction;
use App\Domains\HR\Actions\SaveJobPostingAction;
use App\Domains\HR\Actions\SeedStaffChecklistAction;
use App\Domains\HR\Enums\JobApplicationStatus;
use App\Domains\HR\Enums\JobPostingStatus;
use App\Domains\HR\Enums\OnboardingKind;
use App\Domains\HR\Models\StaffOnboardingItem;
use App\Domains\People\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('publishes a public careers list and hires an applicant into onboarding', function () {
    $admin = actingPeopleAdmin(['hr.manage']);

    $draft = app(SaveJobPostingAction::class)->execute([
        'title' => 'Hidden role',
        'status' => JobPostingStatus::Draft->value,
        'public' => true,
    ]);

    $posting = app(SaveJobPostingAction::class)->execute([
        'title' => 'Arabic Teacher',
        'department' => 'Arabic',
        'employment_type' => 'full_time',
        'status' => JobPostingStatus::Published->value,
        'public' => true,
    ]);

    $this->withoutLocalizationMiddleware()
        ->get(route('public.careers'))
        ->assertOk()
        ->assertSee('Arabic Teacher')
        ->assertDontSee('Hidden role');

    $application = app(SaveJobApplicationAction::class)->execute([
        'job_posting_id' => $posting->id,
        'name' => 'Hussain Didi',
        'email' => 'hussain.hire@example.com',
        'mobile' => '7900001',
        'status' => JobApplicationStatus::Interview->value,
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('hr.applications.hire', $application))
        ->assertRedirect();

    $profile = StaffProfile::query()->where('first_name', 'Hussain')->sole();
    expect($application->fresh()->status)->toBe(JobApplicationStatus::Hired)
        ->and($profile->department)->toBe('Arabic')
        ->and(StaffOnboardingItem::query()->where('staff_profile_id', $profile->id)->where('kind', OnboardingKind::Onboarding)->count())->toBeGreaterThan(0);

    app(SeedStaffChecklistAction::class)->execute($profile->id, OnboardingKind::Offboarding);
    expect(StaffOnboardingItem::query()->where('staff_profile_id', $profile->id)->where('kind', OnboardingKind::Offboarding)->count())->toBeGreaterThan(0);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.postings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('HR/Recruitment/Postings')->has('rows', 2));

    unset($draft);
});
