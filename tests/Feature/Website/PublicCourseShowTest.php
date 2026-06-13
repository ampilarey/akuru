<?php

namespace Tests\Feature\Website;

use App\Domains\Courses\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCourseShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_course_show_loads_cross_domain_relations(): void
    {
        $course = Course::factory()->create([
            'slug' => 'smoke-test-course',
            'title' => 'Smoke Test Course',
            'status' => 'open',
        ]);

        $response = $this->withoutLocalizationMiddleware()
            ->get(route('public.courses.show', ['course' => $course->slug]));

        $response->assertOk();
        $response->assertSee('Smoke Test Course');
    }

    protected function withoutLocalizationMiddleware(): static
    {
        return $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
        ]);
    }
}
