<?php

namespace Tests\Feature\Routes;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PortalRouteNamesTest extends TestCase
{
    public function test_portal_route_names_are_registered(): void
    {
        $names = [
            // The old course portal's addresses, redirects since SIGN_IN_PLAN
            // ID2b: the website's header and old emails still carry them.
            'portal.dashboard',
            'portal.enrollments',
            'portal.payments',
            'portal.certificates',
            'portal.profile',
            'account.set-password',
            'account.set-password.store',
            'account.home',
            'my.enrollments',
            'my.enrollments.export',
            'payment.receipt',
        ];

        foreach ($names as $name) {
            $this->assertTrue(Route::has($name), "Missing route: {$name}");
        }
    }
}
