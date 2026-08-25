<?php

namespace Tests\Feature\Routes;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocalizedRouteNamesTest extends TestCase
{
    public function test_localized_dashboard_and_resource_route_names_are_registered(): void
    {
        $names = [
            'locale',
            'dashboard',
            'enhanced.dashboard',
            'students.index',
            'students.create',
            'students.store',
            'students.show',
            'students.edit',
            'students.update',
            'students.destroy',
            'students.quran-progress',
            'teachers.index',
            'teachers.create',
            'teachers.store',
            'teachers.show',
            'teachers.edit',
            'teachers.update',
            'teachers.destroy',
            'quran-progress.index',
            'quran-progress.create',
            'quran-progress.store',
            'quran-progress.show',
            'quran-progress.edit',
            'quran-progress.update',
            'quran-progress.destroy',
            'quran-progress.update-progress',
            'announcements.index',
            'announcements.create',
            'announcements.store',
            'announcements.show',
            'announcements.edit',
            'announcements.update',
            'announcements.destroy',
            'e-learning.index',
            'e-learning.quran',
            'e-learning.arabic',
            'e-learning.islamic-studies',
            'e-learning.show',
            'profile.edit',
            'profile.update',
            'profile.destroy',
            'notifications.index',
            'absences.index',
            'absences.create',
            'absences.store',
            'absences.edit',
            'absences.update',
            'absences.destroy',
            'substitutions.requests.index',
            'substitutions.requests.create',
            'substitutions.requests.store',
            'substitutions.requests.show',
            'substitutions.requests.edit',
            'substitutions.requests.update',
            'substitutions.requests.destroy',
            'substitutions.requests.take',
            'substitutions.requests.assign',
            'academics.timetable.index',
            'academics.timetable.store',
            'academics.timetable.preview',
            'academics.timetable.copy-from-class',
            'academics.timetable.copy-week',
            'academics.timetable.update',
            'academics.timetable.destroy',
            'academics.timetable.export',
            'academics.bookings.index',
            'academics.bookings.store',
            'academics.bookings.update',
            'academics.bookings.destroy',
            'academics.bookings.export',
            'academics.calendar.index',
            'academics.calendar.store',
            'academics.calendar.update',
            'academics.calendar.destroy',
            'academics.calendar.export',
            'portal.holidays',
        ];

        foreach ($names as $name) {
            $this->assertTrue(Route::has($name), "Missing route: {$name}");
        }
    }
}
