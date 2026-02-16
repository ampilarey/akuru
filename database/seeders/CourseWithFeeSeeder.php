<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds a single course with a registration/joining fee (for payment testing).
 * Run: php artisan db:seed --class=CourseWithFeeSeeder
 */
class CourseWithFeeSeeder extends Seeder
{
    public function run(): void
    {
        $category = CourseCategory::firstOrCreate(
            ['slug' => 'quran-memorization'],
            ['name' => 'Quran Memorization', 'order' => 1]
        );

        Course::updateOrCreate(
            ['slug' => 'test-course-with-joining-fee'],
            [
                'course_category_id' => $category->id,
                'title' => 'Test Course (Joining Fee)',
                'short_desc' => 'Course with a registration fee for testing BML payment.',
                'body' => 'Use this course to test the payment flow. Registration fee: MVR 100.',
                'cover_image' => '',
                'language' => 'mixed',
                'level' => 'all',
                'schedule' => ['To be announced'],
                'fee' => 100.00,
                'registration_fee_amount' => 100.00,
                'registration_fee_currency' => 'MVR',
                'status' => 'open',
                'seats' => 50,
            ]
        );
    }
}
