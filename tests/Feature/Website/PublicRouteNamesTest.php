<?php

namespace Tests\Feature\Website;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicRouteNamesTest extends TestCase
{
    public function test_public_website_route_names_are_registered(): void
    {
        $names = [
            'public.home',
            'public.about',
            'public.careers',
            'public.courses.index',
            'public.courses.show',
            'public.courses.waitlist',
            'public.courses.syllabus',
            'public.funnel.store',
            'public.contact.create',
            'public.contact.store',
            'public.news.index',
            'public.events.index',
            'public.events.register',
            'public.gallery.index',
            'public.daily.index',
            'public.daily.show',
            'public.daily.card',
            'public.daily.subscribe',
            'public.daily.unsubscribe',
            'public.daily.sms-opt-out',
            'public.research.index',
            'public.research.show',
            'public.research.export',
            'public.prayer-times',
            'public.prayer-times.sms-opt-out',
            'public.instructors.show',
            'public.achievements',
            'public.certificates.verify',
            'public.page.show',
            'public.sitemap',
            'admin.pages.index',
            'admin.settings.index',
            'admin.settings.clear-cache',
        ];

        foreach ($names as $name) {
            $this->assertTrue(Route::has($name), "Missing route: {$name}");
        }
    }
}
