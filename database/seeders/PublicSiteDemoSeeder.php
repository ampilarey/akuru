<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PublicSiteDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->createPages();
        $this->createHeroBanners();
        $this->createCourseCategories();
        $this->createCourses();
        $this->createTestimonials();
        $this->createFaqs();
        $this->createPosts();
    }

    private function createPages(): void
    {
        \App\Models\Page::create([
            'title' => 'About Akuru Institute',
            'slug' => 'about',
            'excerpt' => 'Learn about our mission and commitment to Islamic education.',
            'body' => '<h2>Our Mission</h2><p>Providing comprehensive Islamic education in the Maldives.</p>',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    private function createHeroBanners(): void
    {
        \App\Models\HeroBanner::create([
            'title' => 'Welcome to Akuru Institute',
            'subtitle' => 'Comprehensive Islamic Education for All Ages',
            'image_path' => 'banners/hero-1.jpg',
            'cta_text' => 'Explore Courses',
            'cta_url' => '/en/courses',
            'is_active' => true,
            'order' => 1,
        ]);
    }

    private function createCourseCategories(): void
    {
        $categories = [
            ['name' => 'Quran Studies', 'slug' => 'quran-studies', 'order' => 1],
            ['name' => 'Arabic Language', 'slug' => 'arabic-language', 'order' => 2],
            ['name' => 'Islamic Studies', 'slug' => 'islamic-studies', 'order' => 3],
        ];

        foreach ($categories as $category) {
            \App\Models\CourseCategory::create($category);
        }
    }

    private function createCourses(): void
    {
        $category = \App\Models\CourseCategory::first();
        
        \App\Models\Course::create([
            'course_category_id' => $category->id,
            'title' => 'Quran Memorization',
            'slug' => 'quran-memorization',
            'short_desc' => 'Complete Quran memorization program.',
            'body' => '<p>Comprehensive Hifz program with qualified teachers.</p>',
            'cover_image' => 'courses/hifz.jpg',
            'language' => 'mixed',
            'level' => 'all',
            'fee' => 1500.00,
            'status' => 'open',
            'seats' => 20,
        ]);
    }

    private function createTestimonials(): void
    {
        \App\Models\Testimonial::create([
            'name' => 'Fatima Ahmed',
            'role' => 'Parent',
            'quote' => 'Excellent Islamic education for my children.',
            'order' => 1,
            'is_public' => true,
        ]);
    }

    private function createFaqs(): void
    {
        \App\Models\Faq::create([
            'question' => 'What age groups do you cater to?',
            'answer' => 'We offer programs for all ages, from children to adults.',
            'order' => 1,
            'is_public' => true,
        ]);
    }

    private function createPosts(): void
    {
        $author = \App\Models\User::role('admin')->first();
        
        if ($author) {
            \App\Models\Post::create([
                'title' => 'Welcome to Our New Website',
                'slug' => 'welcome-new-website',
                'summary' => 'We are excited to launch our new website.',
                'body' => '<p>Welcome to the new Akuru Institute website!</p>',
                'is_published' => true,
                'published_at' => now(),
                'author_id' => $author->id,
            ]);
        }
    }
}
