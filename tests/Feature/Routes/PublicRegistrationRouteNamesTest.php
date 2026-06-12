<?php

namespace Tests\Feature\Routes;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicRegistrationRouteNamesTest extends TestCase
{
    public function test_public_registration_and_payment_route_names_are_registered(): void
    {
        $names = [
            'courses.checkout.show',
            'courses.checkout.login',
            'courses.register.show',
            'courses.register.start',
            'courses.register.otp',
            'courses.register.verify',
            'courses.register.otp.resend-new',
            'courses.register.set-password',
            'courses.register.set-password.store',
            'courses.register.continue',
            'courses.register.enroll',
            'courses.register.enroll.otp',
            'courses.register.enroll.confirm',
            'courses.register.enroll.resend',
            'courses.register.complete',
            'courses.register.resume',
            'courses.register.payment.retry',
            'checkout.course.show',
            'payments.course.start',
            'payments.return',
            'payments.status',
            'payments.bml.initiate',
            'payments.bml.callback',
            'payments.bml.return',
            'payments.status.by_id',
            'webhooks.bml',
            'public.admissions.create',
            'public.admissions.store',
            'public.admissions.thanks',
            'public.apply',
            'public.apply.store',
        ];

        foreach ($names as $name) {
            $this->assertTrue(Route::has($name), "Missing route: {$name}");
        }
    }
}
