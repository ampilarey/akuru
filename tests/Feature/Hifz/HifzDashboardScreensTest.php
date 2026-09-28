<?php

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
 * The Hifz port, slice 2 (BACKLOG C1, STATUS §5jw): the five role
 * dashboards are Inertia pages at their old addresses, each with the
 * rows its Blade showed, keyed EN/DV/AR; the hub still sends each role to
 * its own (HifzCrossRoleAccessTest pins the redirects and refusals).
 */
function hifzDashboardsSeeded(): void
{
    foreach ([RoleSeeder::class, SchoolSeeder::class, ClassSeeder::class, UserSeeder::class, SurahSeeder::class, HifzDemoSeeder::class] as $seeder) {
        test()->seed($seeder);
    }
}

function hifzRole(string $email): User
{
    $user = User::query()->where('email', $email)->firstOrFail();
    $user->markEmailAsVerified();

    return $user;
}

it('shows the dean ten cards, the haraka leaders, the milestones to approve and three doors', function () {
    hifzDashboardsSeeded();

    $this->withoutLocalizationMiddleware()->actingAs(hifzRole('headmaster@akuru.edu.mv'))
        ->get(route('hifz.dean.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/DeanDashboard')
            ->has('cards', 10)
            ->where('cards.active_programs', fn ($n) => $n >= 1)
            ->has('haraka_leaders')
            ->has('pending_milestones')
            ->where('links.programs', route('hifz.programs.index'))
            ->where('t.hifz_card_active_students', 'Active Students'));

    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['hifz_dean_title', 'hifz_card_active_students', 'hifz_approved_milestones', 'hifz_no_record_today'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }
});

it('shows the supervisor seven cards scoped to their programmes and the pending milestones by pupil', function () {
    hifzDashboardsSeeded();

    $this->withoutLocalizationMiddleware()->actingAs(hifzRole('supervisor@akuru.edu.mv'))
        ->get(route('hifz.supervisor.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/SupervisorDashboard')
            ->has('cards', 7)
            ->has('haraka_leaders')->has('weak_students')
            ->has('pending_milestones', fn (Assert $rows) => $rows->each(fn (Assert $row) => $row->hasAll(['id', 'student', 'type'])->etc()))
            ->where('links.milestones', route('hifz.milestones.index')));
});

it('shows the teacher their roll-up, the pupil their progress, and the parent their child', function () {
    hifzDashboardsSeeded();

    $this->withoutLocalizationMiddleware()->actingAs(hifzRole('teacher@akuru.edu.mv'))
        ->get(route('hifz.teacher.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/TeacherDashboard')
            ->has('assigned_students')->has('programs')->has('today_session')
            ->where('schedule_href', route('teach.schedule')));

    $this->withoutLocalizationMiddleware()->actingAs(hifzRole('student@akuru.edu.mv'))
        ->get(route('hifz.student.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/StudentDashboard')
            ->has('recent_records')->has('milestones'));

    $parent = hifzRole('parent@akuru.edu.mv');
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get(route('hifz.parent.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Hifz/ParentDashboard')
            ->has('children')
            ->has('selected_child.name')
            ->has('week')->has('milestones'));

    // The hub still lands each role on its own dashboard, now with an Inertia visit.
    $this->withoutLocalizationMiddleware()->actingAs($parent)
        ->get('/hifz')->assertRedirect(route('hifz.parent.dashboard'));
});
