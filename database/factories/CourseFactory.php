<?php

namespace Database\Factories;

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);
        $category = CourseCategory::first() ?? CourseCategory::create([
            'name' => 'General',
            'slug' => 'general',
            'order' => 0,
        ]);

        return [
            'course_category_id' => $category->id,
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title).'-'.fake()->unique()->numberBetween(1, 9999),
            'short_desc' => fake()->paragraph(),
            'body' => fake()->paragraphs(3, true),
            'cover_image' => '',
            'language' => 'en',
            'level' => 'all',
            'schedule' => null,
            'fee' => null,
            'registration_fee_amount' => 0,
            'registration_fee_currency' => 'MVR',
            'requires_admin_approval' => true,
            'status' => 'open',
            // The factory never set this, so every factory-made course was a
            // draft — and thirteen tests asserting that a public course page
            // renders were, without anyone noticing, asserting it for content
            // that should never have been public. A factory course is meant to
            // be an ordinary usable course; tests about unpublished states set
            // this explicitly (see CoursePublicationGateTest).
            'workflow_status' => 'published',
            'seats' => null,
        ];
    }
}
