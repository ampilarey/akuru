<?php

use App\Domains\Hifz\Models\HifzMilestone;
use App\Domains\Identity\Models\User;
use Database\Seeders\ClassSeeder;
use Database\Seeders\HifzDemoSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SchoolSeeder;
use Database\Seeders\SurahSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The Hifz port, slice 3 (BACKLOG C1, STATUS §5jx): the milestones list
 * and the reports are Inertia pages at their old addresses, keyed
 * EN/DV/AR. Review and Approve show only where the policy and the status
 * allow them; the reports stay gated on `view_hifz_reports`
 * (HifzCrossRoleAccessTest pins the refusal) and scoped off the dean.
 */
function hifzReportsSeeded(): void
{
    foreach ([RoleSeeder::class, SchoolSeeder::class, ClassSeeder::class, UserSeeder::class, SurahSeeder::class, HifzDemoSeeder::class] as $seeder) {
        test()->seed($seeder);
    }
}

function hifzReportUser(string $email): User
{
    $user = User::query()->where('email', $email)->firstOrFail();
    $user->markEmailAsVerified();

    return $user;
}

it('lists the milestones with Review for the supervisor on a pending one and Approve for the dean on a reviewed one', function () {
    hifzReportsSeeded();
    $pending = HifzMilestone::query()->where('status', 'pending')->firstOrFail();

    $this->withoutLocalizationMiddleware()->actingAs(hifzReportUser('supervisor@akuru.edu.mv'))
        ->get(route('hifz.milestones.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/Milestones')
            ->where('milestones.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $pending->id && $row['status'] === 'pending' && $row['can_review'] === true && $row['can_approve'] === false))
            ->where('t.hifz_ms_supervisor_reviewed', 'Supervisor reviewed'));

    $pending->update(['status' => 'supervisor_reviewed']);

    $this->withoutLocalizationMiddleware()->actingAs(hifzReportUser('headmaster@akuru.edu.mv'))
        ->get(route('hifz.milestones.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/Milestones')
            ->where('milestones.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $pending->id && $row['can_approve'] === true && $row['can_review'] === false)));

    // Approve comes back to the list with the flash the shell renders, keyed.
    $this->withoutLocalizationMiddleware()->actingAs(hifzReportUser('headmaster@akuru.edu.mv'))
        ->from(route('hifz.milestones.index'))
        ->post(route('hifz.milestones.approve', $pending))
        ->assertRedirect(route('hifz.milestones.index'))
        ->assertSessionHas('success', 'Milestone approved.');

    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['hifz_milestones_title', 'hifz_ms_supervisor_reviewed', 'hifz_flash_milestone_approved', 'hifz_reports_title', 'hifz_report_milestones', 'hifz_report_empty_teacher_completion'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }
});

it('opens the reports hub with five doors and the export, and each report as its page', function () {
    hifzReportsSeeded();
    $dean = hifzReportUser('headmaster@akuru.edu.mv');

    $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->get(route('hifz.reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/Reports')
            ->has('reports', 5)
            ->where('reports.4.key', 'milestones')
            ->where('reports.4.href', route('hifz.reports.milestones'))
            ->where('export_href', route('hifz.reports.export', ['type' => 'sessions'])));

    foreach ([
        'hifz.reports.weak-students' => ['weak_students', 'weak_count', 'hifz_weak_unit'],
        'hifz.reports.haraka-mistakes' => ['haraka', 'total_haraka', null],
        'hifz.reports.parent-follow-up' => ['parent_follow_up', 'date', null],
        'hifz.reports.teacher-completion' => ['teacher_completion', null, null],
    ] as $route => [$report, $figure, $unit]) {
        $this->withoutLocalizationMiddleware()->actingAs($dean)
            ->get(route($route))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Hifz/ReportRows')
                ->where('report', $report)
                ->where('figure', $figure)
                ->where('unit', $unit)
                ->has('rows')
                ->where('back_href', route('hifz.reports.index')));
    }

    $this->withoutLocalizationMiddleware()->actingAs($dean)
        ->get(route('hifz.reports.milestones'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/ReportMilestones')
            ->has('milestones.data', fn (Assert $rows) => $rows->each(fn (Assert $row) => $row->hasAll(['id', 'student', 'program', 'type', 'status'])->etc())));
});

it('scopes the milestone report to the supervisor\'s programmes', function () {
    hifzReportsSeeded();
    $supervisor = hifzReportUser('supervisor@akuru.edu.mv');
    $programIds = app(\App\Domains\Hifz\Services\HifzScopeService::class)->assignedProgramIds($supervisor);
    $page = $this->withoutLocalizationMiddleware()->actingAs($supervisor)
        ->get(route('hifz.reports.milestones'))
        ->assertOk()
        ->inertiaPage();

    $ids = collect($page['props']['milestones']['data'])->pluck('id');
    expect(HifzMilestone::query()->whereIn('id', $ids)->whereNotIn('hifz_program_id', $programIds)->exists())->toBeFalse();
});
