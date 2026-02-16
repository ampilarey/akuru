<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseCategory;
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
            'slug' => \Illuminate\Support\Str::slug($title) . '-' . fake()->unique()->numberBetween(1, 9999),
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
            'seats' => null,
        ];
    }
}
