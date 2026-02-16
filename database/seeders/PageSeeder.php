<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Seed the pages table with privacy policy and terms of service.
     */
    public function run(): void
    {
        $pages = [
            [
                'title' => 'About Us',
                'slug' => 'about',
                'excerpt' => 'Learn about Akuru Institute and our mission to provide quality Islamic education.',
                'body' => "About Akuru Institute\n\nAkuru Institute is dedicated to providing quality Islamic education in the Maldives. We offer courses in Quran memorization, Arabic language, and Islamic studies for students of all ages.\n\nOur Mission\n\nTo nurture a generation of Muslims who are grounded in the Quran and Sunna, with strong Arabic language skills and sound Islamic knowledge.\n\nContact\n\nFor more information, visit our contact page or email info@akuru.edu.mv.",
                'is_published' => true,
                'published_at' => now(),
            ],
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'excerpt' => 'How Akuru Institute collects, uses, and protects your personal information.',
                'body' => "Information We Collect\n\nWe collect information you provide when applying for admission, contacting us, or subscribing to our newsletter. This may include name, contact details, educational background, and course preferences.\n\nHow We Use Your Information\n\nWe use your information to process admission applications, respond to inquiries, send relevant updates about our courses and events, and improve our services.\n\nCookies\n\nOur website uses cookies to enhance your experience. You can control cookie preferences through our cookie consent banner.\n\nData Protection\n\nWe implement appropriate security measures to protect your personal information.\n\nContact\n\nFor privacy-related questions, contact us at info@akuru.edu.mv.",
                'is_published' => true,
                'published_at' => now(),
            ],
            [
                'title' => 'Terms of Service',
                'slug' => 'terms',
                'excerpt' => 'Terms and conditions for using Akuru Institute services.',
                'body' => "General Terms\n\nBy using our website and services, you agree to these terms.\n\nUse of Services\n\nYou must provide accurate information when applying or contacting us. Our services are for educational purposes in accordance with our mission.\n\nIntellectual Property\n\nAll content on this website is owned by Akuru Institute and is protected by copyright.\n\nContact\n\nFor questions about these terms, contact us at info@akuru.edu.mv.",
                'is_published' => true,
                'published_at' => now(),
            ],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(
                ['slug' => $page['slug']],
                $page
            );
        }
    }
}
