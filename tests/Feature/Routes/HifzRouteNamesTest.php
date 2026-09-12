<?php

namespace Tests\Feature\Routes;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class HifzRouteNamesTest extends TestCase
{
    public function test_hifz_route_names_are_registered(): void
    {
        $names = [
            'hifz.hub',
            'hifz.teacher.dashboard',
            'hifz.supervisor.dashboard',
            'hifz.dean.dashboard',
            'hifz.student.dashboard',
            'hifz.parent.dashboard',
            'hifz.programs.index',
            'hifz.programs.create',
            'hifz.programs.store',
            'hifz.programs.show',
            'hifz.programs.edit',
            'hifz.programs.update',
            // `hifz.programs.destroy` is deliberately absent: the controller
            // has no `destroy` method, so that route answered 500 on every hit
            // while this test passed on the name alone.
            'hifz.programs.assign-supervisor',
            'hifz.enrollments.index',
            'hifz.enrollments.create',
            'hifz.enrollments.store',
            // F5 (ADR-025): `hifz.sessions.*`, `hifz.session-records.*`,
            // `hifz.students.history` and `hifz.mistakes.*` are gone — the
            // three-lane session sheet lives on the engine now
            // (`teach.quran-sessions.*`). `hifz.quran.*` moved with the Qur'an
            // dataset and is registered below as `quran.*`.
            'hifz.milestones.index',
            'hifz.milestones.store',
            'hifz.milestones.supervisor-review',
            'hifz.milestones.approve',
            'hifz.milestones.reject',
            'hifz.reports.index',
            'hifz.reports.weak-students',
            'hifz.reports.haraka-mistakes',
            'hifz.reports.parent-follow-up',
            'hifz.reports.teacher-completion',
            'hifz.reports.milestones',
            'hifz.reports.export',
        ];

        foreach ($names as $name) {
            $this->assertTrue(Route::has($name), "Missing route: {$name}");
        }
    }

    public function test_mushaf_editorial_route_names_moved_to_the_engine(): void
    {
        $moved = [
            'quran.mushafs.index',
            'quran.mushafs.create',
            'quran.mushafs.store',
            'quran.mushafs.show',
            'quran.mushafs.approve',
            'quran.mushafs.lock',
            'quran.mushafs.import-ayah',
            'quran.pages.show',
            'quran.pages.positions.store',
        ];

        foreach ($moved as $name) {
            $this->assertTrue(Route::has($name), "Missing route: {$name}");
        }

        // The old names must not survive alongside the new ones: two live
        // systems over one dataset is exactly what F5 exists to end.
        foreach (['hifz.quran.mushafs.index', 'hifz.sessions.index', 'hifz.mistakes.store'] as $retired) {
            $this->assertFalse(Route::has($retired), "Retired route still registered: {$retired}");
        }
    }
}
