<?php

namespace Tests\Feature\Routes;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminRouteNamesTest extends TestCase
{
    public function test_admin_route_names_are_registered(): void
    {
        $names = [
            'admin.users.index',
            'admin.users.destroy',
            'admin.enrollments.index',
            'admin.enrollments.export',
            'admin.enrollments.payments',
            'admin.enrollments.show',
            'admin.enrollments.activate',
            'admin.enrollments.reject',
            'admin.instructors.index',
            'admin.instructors.create',
            'admin.instructors.store',
            'admin.instructors.edit',
            'admin.instructors.update',
            'admin.instructors.destroy',
            'admin.settings.index',
            'admin.settings.clear-cache',
            'admin.pages.index',
            'admin.pages.create',
            'admin.pages.store',
            'admin.pages.show',
            'admin.pages.edit',
            'admin.pages.update',
            'admin.pages.destroy',
            'admin.courses.index',
            'admin.courses.create',
            'admin.courses.store',
            'admin.courses.edit',
            'admin.courses.update',
            'admin.courses.destroy',
            'admin.leads.index',
            'admin.leads.export',
            'admin.funnel.index',
            'admin.funnel.export',
            'admin.daily-content.index',
            'admin.daily-content.export',
            'admin.daily-content.queue',
            'admin.daily-content.create',
            'admin.daily-content.ayah-preview',
            'analytics.index',
            'analytics.reports',
            'analytics.reports.generate',
            'analytics.reports.download',
            'analytics.reports.delete',
        ];

        foreach ($names as $name) {
            $this->assertTrue(Route::has($name), "Missing route: {$name}");
        }
    }
}
