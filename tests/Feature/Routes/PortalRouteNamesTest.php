<?php

namespace Tests\Feature\Routes;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PortalRouteNamesTest extends TestCase
{
    public function test_portal_route_names_are_registered(): void
    {
        $names = [
            'portal.dashboard',
            'portal.enrollments',
            'portal.payments',
            'portal.certificates',
            'portal.profile',
            'portal.profile.update',
            'account.set-password',
            'account.set-password.store',
            'my.enrollments',
            'payment.receipt',
        ];

        foreach ($names as $name) {
            $this->assertTrue(Route::has($name), "Missing route: {$name}");
        }
    }
}
