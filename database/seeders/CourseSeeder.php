<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Course, CourseCategory};

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing categories
        $quranCategory = CourseCategory::where('slug', 'quran-memorization')->first();
        $arabicCategory = CourseCategory::where('slug', 'arabic-language')->first();
        $islamicCategory = CourseCategory::where('slug', 'islamic-studies')->first();
        $quranStudiesCategory = CourseCategory::where('slug', 'quran-studies')->first();
        
        // Create additional categories if they don't exist
        $tajweedCategory = CourseCategory::firstOrCreate(
            ['slug' => 'tajweed'],
            ['name' => 'Tajweed', 'order' => 4]
        );
        $hadithCategory = CourseCategory::firstOrCreate(
            ['slug' => 'hadith-studies'],
            ['name' => 'Hadith Studies', 'order' => 5]
        );
        $fiqhCategory = CourseCategory::firstOrCreate(
            ['slug' => 'fiqh'],
            ['name' => 'Fiqh (Islamic Jurisprudence)', 'order' => 6]
        );
        $adultCategory = CourseCategory::firstOrCreate(
            ['slug' => 'adult-education'],
            ['name' => 'Adult Education', 'order' => 7]
        );
        $childrenCategory = CourseCategory::firstOrCreate(
            ['slug' => 'children-programs'],
            ['name' => 'Children Programs', 'order' => 8]
        );

        $courses = [
            // Quran Memorization Courses
            [
                'course_category_id' => $quranCategory->id,
                'title' => 'Complete Quran Memorization (Hifz)',
                'slug' => 'complete-quran-memorization-hifz',
                'short_desc' => 'Complete memorization of the Holy Quran with proper Tajweed and understanding.',
                'body' => "This comprehensive program is designed for students who wish to memorize the entire Quran. The course includes:

• Complete memorization of all 30 Juz (parts) of the Quran
• Proper Tajweed rules and pronunciation
• Understanding of meanings and context
• Regular testing and evaluation
• Individual attention and guidance
• Flexible timing for different age groups

The program is structured over 2-3 years with regular assessments and progress tracking. Students will be assigned qualified Hafiz teachers who will guide them through each step of the memorization process.",
                'cover_image' => 'courses/quran-hifz.jpg',
                'language' => 'mixed',
                'level' => 'all',
                'schedule' => [
                    'Monday to Friday: 6:00 PM - 8:00 PM',
                    'Saturday: 9:00 AM - 11:00 AM',
                    'Sunday: 2:00 PM - 4:00 PM'
                ],
                'fee' => 500.00,
                'registration_fee_amount' => 500.00,
                'registration_fee_currency' => 'MVR',
                'status' => 'open',
                'seats' => 25,
            ],
            [
                'course_category_id' => $quranCategory->id,
                'title' => 'Selected Surahs Memorization',
                'slug' => 'selected-surahs-memorization',
                'short_desc' => 'Memorize important Surahs with their meanings and applications.',
                'body' => "This course focuses on memorizing key Surahs that are commonly recited in prayers and special occasions:

• Surah Al-Fatiha and last 10 Surahs
• Surah Al-Baqarah (selected verses)
• Surah Al-Kahf (complete)
• Surah Al-Mulk and Al-Rahman
• Surah Al-Waqiah and Al-Muzzammil
• Understanding of meanings and context
• Practical application in daily prayers

Perfect for beginners and those who want to strengthen their prayer recitation.",
                'cover_image' => 'courses/selected-surahs.jpg',
                'language' => 'mixed',
                'level' => 'all',
                'schedule' => [
                    'Tuesday & Thursday: 7:00 PM - 8:30 PM',
                    'Saturday: 10:00 AM - 11:30 AM'
                ],
                'fee' => 200.00,
                'registration_fee_amount' => 200.00,
                'registration_fee_currency' => 'MVR',
                'status' => 'open',
                'seats' => 30,
            ],

            // Arabic Language Courses
            [
                'course_category_id' => $arabicCategory->id,
                'title' => 'Arabic Language for Beginners',
                'slug' => 'arabic-language-beginners',
                'short_desc' => 'Learn Arabic from scratch with focus on reading, writing, and basic conversation.',
                'body' => "A comprehensive Arabic language course designed for complete beginners:

• Arabic alphabet and pronunciation
• Basic grammar and sentence structure
• Essential vocabulary (500+ words)
• Reading and writing skills
• Basic conversation practice
• Introduction to Arabic culture
• Islamic terminology and phrases

The course uses modern teaching methods with interactive activities and practical exercises.",
                'cover_image' => 'courses/arabic-beginners.jpg',
                'language' => 'mixed',
                'level' => 'all',
                'schedule' => [
                    'Monday, Wednesday, Friday: 6:30 PM - 8:00 PM',
                    'Saturday: 9:00 AM - 10:30 AM'
                ],
                'fee' => 300.00,
                'registration_fee_amount' => 300.00,
                'registration_fee_currency' => 'MVR',
                'status' => 'open',
                'seats' => 20,
            ],
            [
                'course_category_id' => $arabicCategory->id,
                'title' => 'Advanced Arabic Grammar',
                'slug' => 'advanced-arabic-grammar',
                'short_desc' => 'Master advanced Arabic grammar and literature for deeper understanding of Islamic texts.',
                'body' => "For students who have completed basic Arabic and want to advance further:

• Advanced grammar rules and structures
• Classical Arabic literature
• Understanding of Quranic Arabic
• Hadith terminology and interpretation
• Arabic poetry and prose
• Translation skills
• Research methodology

This course prepares students for advanced Islamic studies and research.",
                'cover_image' => 'courses/arabic-advanced.jpg',
                'language' => 'mixed',
                'level' => 'adult',
                'schedule' => [
                    'Tuesday & Thursday: 7:30 PM - 9:00 PM',
                    'Sunday: 10:00 AM - 12:00 PM'
                ],
                'fee' => 400.00,
                'status' => 'open',
                'seats' => 15,
            ],

            // Islamic Studies Courses
            [
                'course_category_id' => $islamicCategory->id,
                'title' => 'Fundamentals of Islam',
                'slug' => 'fundamentals-of-islam',
                'short_desc' => 'Comprehensive study of Islamic beliefs, practices, and principles.',
                'body' => "A foundational course covering the essential aspects of Islam:

• Five Pillars of Islam
• Six Articles of Faith
• Islamic history and civilization
• Prophet Muhammad's (PBUH) life and teachings
• Islamic ethics and morality
• Family and social values in Islam
• Contemporary Islamic issues

This course is suitable for new Muslims and those seeking to strengthen their Islamic knowledge.",
                'cover_image' => 'courses/islamic-fundamentals.jpg',
                'language' => 'mixed',
                'level' => 'all',
                'schedule' => [
                    'Monday & Wednesday: 7:00 PM - 8:30 PM',
                    'Saturday: 2:00 PM - 3:30 PM'
                ],
                'fee' => 250.00,
                'status' => 'open',
                'seats' => 25,
            ],
            [
                'course_category_id' => $islamicCategory->id,
                'title' => 'Islamic History and Civilization',
                'slug' => 'islamic-history-civilization',
                'short_desc' => 'Explore the rich history of Islamic civilization and its contributions to humanity.',
                'body' => "An in-depth study of Islamic history and its global impact:

• Life of Prophet Muhammad (PBUH)
• The Rightly Guided Caliphs
• Umayyad and Abbasid dynasties
• Islamic Golden Age
• Contributions to science, medicine, and philosophy
• Islamic art and architecture
• Modern Islamic movements
• Contemporary Muslim world

Students will gain a comprehensive understanding of Islamic civilization's role in world history.",
                'cover_image' => 'courses/islamic-history.jpg',
                'language' => 'mixed',
                'level' => 'adult',
                'schedule' => [
                    'Tuesday & Thursday: 6:00 PM - 7:30 PM',
                    'Sunday: 11:00 AM - 12:30 PM'
                ],
                'fee' => 300.00,
                'status' => 'open',
                'seats' => 20,
            ],

            // Tajweed Courses
            [
                'course_category_id' => $tajweedCategory->id,
                'title' => 'Tajweed for Beginners',
                'slug' => 'tajweed-beginners',
                'short_desc' => 'Learn proper Quran recitation with correct pronunciation and rules.',
                'body' => "Master the art of beautiful Quran recitation:

• Basic Tajweed rules and principles
• Correct pronunciation of Arabic letters
• Makharij (articulation points)
• Sifaat (characteristics of letters)
• Rules of Noon and Meem
• Rules of Madd (elongation)
• Practical recitation practice
• Common mistakes and how to avoid them

This course is essential for anyone who wants to recite the Quran properly.",
                'cover_image' => 'courses/tajweed-beginners.jpg',
                'language' => 'mixed',
                'level' => 'all',
                'schedule' => [
                    'Monday, Wednesday, Friday: 6:00 PM - 7:00 PM',
                    'Saturday: 9:00 AM - 10:00 AM'
                ],
                'fee' => 200.00,
                'status' => 'open',
                'seats' => 30,
            ],

            // Children Programs
            [
                'course_category_id' => $childrenCategory->id,
                'title' => 'Islamic Education for Children (Ages 5-12)',
                'slug' => 'islamic-education-children',
                'short_desc' => 'Fun and interactive Islamic education designed specifically for children.',
                'body' => "A specially designed program for young children to learn Islam in a fun and engaging way:

• Basic Islamic beliefs and practices
• Stories of Prophets and Companions
• Islamic manners and etiquette
• Quran memorization (short Surahs)
• Arabic alphabet and basic reading
• Islamic songs and activities
• Arts and crafts with Islamic themes
• Character building and moral values

The program uses age-appropriate teaching methods with games, stories, and interactive activities.",
                'cover_image' => 'courses/children-islamic.jpg',
                'language' => 'mixed',
                'level' => 'kids',
                'schedule' => [
                    'Saturday: 9:00 AM - 11:00 AM',
                    'Sunday: 9:00 AM - 11:00 AM'
                ],
                'fee' => 150.00,
                'status' => 'open',
                'seats' => 35,
            ],

            // Adult Education
            [
                'course_category_id' => $adultCategory->id,
                'title' => 'Islamic Studies for Adults',
                'slug' => 'islamic-studies-adults',
                'short_desc' => 'Comprehensive Islamic education program designed for working adults.',
                'body' => "A flexible program designed for working adults who want to deepen their Islamic knowledge:

• Flexible scheduling options
• Evening and weekend classes
• Online learning support
• Practical application of Islamic principles
• Contemporary Islamic issues
• Family and workplace ethics
• Islamic finance and business ethics
• Community leadership and service

Perfect for professionals who want to balance work and religious education.",
                'cover_image' => 'courses/adult-islamic.jpg',
                'language' => 'mixed',
                'level' => 'adult',
                'schedule' => [
                    'Monday & Wednesday: 8:00 PM - 9:30 PM',
                    'Saturday: 2:00 PM - 4:00 PM'
                ],
                'fee' => 350.00,
                'status' => 'open',
                'seats' => 20,
            ],

            // Upcoming Courses
            [
                'course_category_id' => $hadithCategory->id,
                'title' => 'Hadith Studies and Interpretation',
                'slug' => 'hadith-studies-interpretation',
                'short_desc' => 'Deep dive into Hadith sciences, authentication, and interpretation.',
                'body' => "Advanced course on Hadith studies for serious students:

• Introduction to Hadith sciences
• Major Hadith collections
• Authentication methods
• Understanding Hadith terminology
• Practical application of Hadith
• Contemporary Hadith issues
• Research methodology

This course will be available starting next semester.",
                'cover_image' => 'courses/hadith-studies.jpg',
                'language' => 'mixed',
                'level' => 'adult',
                'schedule' => [
                    'To be announced'
                ],
                'fee' => 400.00,
                'status' => 'upcoming',
                'seats' => 15,
            ],
        ];

        foreach ($courses as $course) {
            Course::create($course);
        }
    }
}