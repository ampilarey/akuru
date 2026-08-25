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
            'public.contact.create',
            'public.contact.store',
            'public.news.index',
            'public.events.index',
            'public.gallery.index',
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
