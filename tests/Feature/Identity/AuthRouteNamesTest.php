<?php

namespace Tests\Feature\Identity;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthRouteNamesTest extends TestCase
{
    public function test_identity_auth_route_names_are_registered(): void
    {
        $names = [
            'login',
            'register',
            'password.request',
            'password.reset',
            'otp.login.form',
            'otp.request',
            'otp.verify.form',
        ];

        foreach ($names as $name) {
            $this->assertTrue(Route::has($name), "Missing route: {$name}");
        }
    }
}
