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
            'hifz.programs.destroy',
            'hifz.programs.assign-supervisor',
            'hifz.enrollments.index',
            'hifz.enrollments.create',
            'hifz.enrollments.store',
            'hifz.sessions.index',
            'hifz.sessions.create',
            'hifz.sessions.edit',
            'hifz.sessions.update',
            'hifz.sessions.records.bulk',
            'hifz.sessions.review',
            'hifz.session-records.update',
            'hifz.session-records.review',
            'hifz.session-records.quran-page',
            'hifz.students.history',
            'hifz.mistakes.store',
            'hifz.mistakes.destroy',
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
            'hifz.quran.mushafs.index',
            'hifz.quran.mushafs.create',
            'hifz.quran.mushafs.store',
            'hifz.quran.mushafs.show',
            'hifz.quran.mushafs.approve',
            'hifz.quran.mushafs.lock',
            'hifz.quran.mushafs.import-ayah',
            'hifz.quran.pages.show',
            'hifz.quran.words.index',
            'hifz.quran.pages.positions.store',
        ];

        foreach ($names as $name) {
            $this->assertTrue(Route::has($name), "Missing route: {$name}");
        }
    }
}
