<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The two full dashboards (docs/ADMIN_PANEL.md L24; C9 slice 13, STATUS
 * §5jo): Inertia pages with every string keyed EN/DV/AR — the super admin's
 * numbers with the last enrolments, system health and the prayer card, and
 * the supervisor's roll, staff and Quran progress — each carrying the way
 * home to its workspace.
 */
it('renders the super admin numbers dashboard as props, with the way home and the keyed strings', function () {
    Role::findOrCreate('supervisor', 'web');
    $super = actingSystemAdmin();
    seedPrayerTimesFixture();

    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('dashboard.numbers'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/NumbersDashboard')
            ->where('home', route('admin.index'))
            ->where('stats.total_users', 1)->where('stats.total_enrollments', 0)->where('stats.revenue_total', '0')
            ->has('stats.database_size')->where('health.database', 'healthy')->has('health.storage')->has('health.sms_gateway')
            ->where('recent', [])->has('islamic_date.month_name')->has('prayer_times')
            ->where('links.users', route('admin.users.index'))->where('links.logout', route('logout'))
            ->where('t.numbers_title', 'Super Admin Dashboard')->where('t.numbers_health_title', 'System Health')->where('t.numbers_prayer_fajr', 'Fajr'));

    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['numbers_title', 'numbers_hint', 'numbers_recent_title', 'numbers_health_title', 'numbers_prayer_title', 'supervisor_title', 'supervisor_students', 'supervisor_teachers'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }

    // Each dashboard is its role's alone.
    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('dashboard.supervisor'))->assertForbidden();
});

it('renders the supervisor dashboard as props, with the Hifz door only for those who may open it', function () {
    Role::findOrCreate('super_admin', 'web');
    $supervisor = User::factory()->create();
    $supervisor->assignRole(Role::findOrCreate('supervisor', 'web'));

    $this->withoutLocalizationMiddleware()->actingAs($supervisor)->get(route('dashboard.supervisor'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/SupervisorDashboard')
            ->where('home', route('school.index'))->where('hifz_href', route('hifz.supervisor.dashboard'))
            ->where('stats.students_on_roll', 0)->where('stats.teachers_teaching', 0)->where('stats.quran_progress_today', 0)
            ->where('can_hifz', $supervisor->can('view_hifz_programs'))
            ->where('t.supervisor_title', 'Supervisor Dashboard')->where('t.supervisor_quran_today', 'Quran Progress Today'));

    $this->withoutLocalizationMiddleware()->actingAs($supervisor)->get(route('dashboard.numbers'))->assertForbidden();
});
