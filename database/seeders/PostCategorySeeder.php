<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PostCategory;

class PostCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'News & Announcements',
                'slug' => 'news-announcements',
                'description' => 'Latest news and important announcements from Akuru Institute',
                'color' => 'blue',
                'icon' => 'news',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Academic Updates',
                'slug' => 'academic-updates',
                'description' => 'Academic news, curriculum updates, and educational developments',
                'color' => 'green',
                'icon' => 'academic',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Quran & Islamic Studies',
                'slug' => 'quran-islamic-studies',
                'description' => 'Articles about Quran studies, Islamic teachings, and religious education',
                'color' => 'brandMaroon',
                'icon' => 'quran',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'Events & Activities',
                'slug' => 'events-activities',
                'description' => 'Upcoming events, activities, and community engagement',
                'color' => 'purple',
                'icon' => 'event',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name' => 'Student Achievements',
                'slug' => 'student-achievements',
                'description' => 'Celebrating student accomplishments and success stories',
                'color' => 'yellow',
                'icon' => 'education',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Community News',
                'slug' => 'community-news',
                'description' => 'Community updates, partnerships, and local engagement',
                'color' => 'indigo',
                'icon' => 'community',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'name' => 'Educational Resources',
                'slug' => 'educational-resources',
                'description' => 'Learning materials, study guides, and educational content',
                'color' => 'pink',
                'icon' => 'education',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'name' => 'General',
                'slug' => 'general',
                'description' => 'General articles and miscellaneous content',
                'color' => 'gray',
                'icon' => 'general',
                'sort_order' => 8,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            PostCategory::create($category);
        }
    }
}