<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Event;
use Carbon\Carbon;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $events = [
            [
                'title' => 'Annual Quran Competition 2024',
                'slug' => 'annual-quran-competition-2024',
                'description' => 'Join us for our prestigious annual Quran recitation competition featuring students from all levels. This event showcases the beautiful recitation of the Holy Quran and celebrates our students\' dedication to memorization and proper pronunciation.',
                'short_description' => 'Prestigious Quran recitation competition for all students',
                'cover_image' => 'events/quran-competition.jpg',
                'location' => 'Akuru Institute Main Hall',
                'address' => 'Akuru Institute, Malé, Maldives',
                'start_date' => Carbon::now()->addDays(30)->setTime(9, 0),
                'end_date' => Carbon::now()->addDays(30)->setTime(17, 0),
                'type' => 'competition',
                'status' => 'published',
                'registration_type' => 'required',
                'max_attendees' => 200,
                'registration_fee' => 0,
                'registration_deadline' => Carbon::now()->addDays(25),
                'registration_start' => Carbon::now()->addDays(-5),
                'registration_instructions' => 'Please register by providing your name, contact information, and the Surahs you will be reciting.',
                'requirements' => [
                    'Bring your own Quran',
                    'Dress in appropriate Islamic attire',
                    'Arrive 30 minutes before your scheduled time',
                    'Prepare 3-5 Surahs for recitation'
                ],
                'speakers' => [
                    [
                        'name' => 'Sheikh Ahmed Ibrahim',
                        'title' => 'Chief Judge',
                        'bio' => 'Renowned Quran reciter and Islamic scholar'
                    ],
                    [
                        'name' => 'Dr. Aisha Mohamed',
                        'title' => 'Judge',
                        'bio' => 'Expert in Tajweed and Quranic studies'
                    ]
                ],
                'schedule' => [
                    '9:00 AM - Opening Ceremony',
                    '9:30 AM - Kids Category (Ages 6-12)',
                    '11:00 AM - Youth Category (Ages 13-18)',
                    '2:00 PM - Adult Category (Ages 19+)',
                    '4:00 PM - Award Ceremony',
                    '5:00 PM - Closing Remarks'
                ],
                'contact_info' => [
                    'phone' => '+960 797 2434',
                    'email' => 'events@akuru.edu.mv',
                    'person' => 'Event Coordinator'
                ],
                'is_featured' => true,
                'is_public' => true,
                'send_reminders' => true,
                'reminder_days' => 3,
            ],
            [
                'title' => 'Arabic Language Workshop',
                'slug' => 'arabic-language-workshop',
                'description' => 'An intensive workshop designed to improve your Arabic language skills. Learn practical Arabic conversation, grammar, and vocabulary that you can use in daily life and Islamic studies.',
                'short_description' => 'Intensive Arabic language skills workshop',
                'cover_image' => 'events/arabic-workshop.jpg',
                'location' => 'Classroom 2A',
                'address' => 'Akuru Institute, Malé, Maldives',
                'start_date' => Carbon::now()->addDays(14)->setTime(14, 0),
                'end_date' => Carbon::now()->addDays(14)->setTime(18, 0),
                'type' => 'workshop',
                'status' => 'published',
                'registration_type' => 'optional',
                'max_attendees' => 25,
                'registration_fee' => 50.00,
                'registration_deadline' => Carbon::now()->addDays(10),
                'registration_start' => Carbon::now()->addDays(-2),
                'registration_instructions' => 'Registration includes workshop materials and refreshments.',
                'requirements' => [
                    'Basic Arabic knowledge preferred',
                    'Bring notebook and pen',
                    'Laptop recommended for digital resources'
                ],
                'speakers' => [
                    [
                        'name' => 'Ustadh Hassan Ali',
                        'title' => 'Arabic Language Instructor',
                        'bio' => 'Native Arabic speaker with 10+ years teaching experience'
                    ]
                ],
                'schedule' => [
                    '2:00 PM - Welcome and Introduction',
                    '2:30 PM - Arabic Grammar Review',
                    '3:30 PM - Conversation Practice',
                    '4:30 PM - Vocabulary Building',
                    '5:30 PM - Q&A Session'
                ],
                'contact_info' => [
                    'phone' => '+960 797 2434',
                    'email' => 'arabic@akuru.edu.mv'
                ],
                'is_featured' => false,
                'is_public' => true,
                'send_reminders' => true,
                'reminder_days' => 1,
            ],
            [
                'title' => 'Islamic History Seminar',
                'slug' => 'islamic-history-seminar',
                'description' => 'Explore the rich history of Islamic civilization and its contributions to the world. This seminar covers the Golden Age of Islam, great Muslim scholars, and the spread of Islamic knowledge across different cultures.',
                'short_description' => 'Comprehensive seminar on Islamic civilization history',
                'cover_image' => 'events/islamic-history.jpg',
                'location' => 'Conference Room',
                'address' => 'Akuru Institute, Malé, Maldives',
                'start_date' => Carbon::now()->addDays(21)->setTime(19, 0),
                'end_date' => Carbon::now()->addDays(21)->setTime(21, 0),
                'type' => 'seminar',
                'status' => 'published',
                'registration_type' => 'none',
                'max_attendees' => 100,
                'registration_fee' => 0,
                'registration_deadline' => null,
                'registration_start' => null,
                'requirements' => [
                    'Open to all community members',
                    'No prior knowledge required'
                ],
                'speakers' => [
                    [
                        'name' => 'Dr. Mohamed Shareef',
                        'title' => 'Islamic History Professor',
                        'bio' => 'Expert in Islamic civilization and Middle Eastern history'
                    ]
                ],
                'schedule' => [
                    '7:00 PM - Introduction to Islamic History',
                    '7:30 PM - The Golden Age of Islam',
                    '8:00 PM - Great Muslim Scholars and Scientists',
                    '8:30 PM - Islamic Art and Architecture',
                    '9:00 PM - Q&A and Discussion'
                ],
                'contact_info' => [
                    'phone' => '+960 797 2434',
                    'email' => 'info@akuru.edu.mv'
                ],
                'is_featured' => true,
                'is_public' => true,
                'send_reminders' => false,
            ],
            [
                'title' => 'Tajweed Masterclass',
                'slug' => 'tajweed-masterclass',
                'description' => 'Perfect your Quran recitation with our comprehensive Tajweed masterclass. Learn the proper pronunciation, articulation points, and rules that make your recitation beautiful and correct.',
                'short_description' => 'Master the art of beautiful Quran recitation',
                'cover_image' => 'events/tajweed-masterclass.jpg',
                'location' => 'Quran Study Room',
                'address' => 'Akuru Institute, Malé, Maldives',
                'start_date' => Carbon::now()->addDays(7)->setTime(16, 0),
                'end_date' => Carbon::now()->addDays(7)->setTime(18, 0),
                'type' => 'workshop',
                'status' => 'published',
                'registration_type' => 'required',
                'max_attendees' => 15,
                'registration_fee' => 25.00,
                'registration_deadline' => Carbon::now()->addDays(3),
                'registration_start' => Carbon::now()->addDays(-1),
                'registration_instructions' => 'Bring your own Quran and notebook. Limited seats available.',
                'requirements' => [
                    'Basic Quran reading ability',
                    'Bring your own Quran',
                    'Commitment to practice at home'
                ],
                'speakers' => [
                    [
                        'name' => 'Qari Ibrahim Hassan',
                        'title' => 'Tajweed Specialist',
                        'bio' => 'Certified Tajweed instructor with Ijazah in Quran recitation'
                    ]
                ],
                'schedule' => [
                    '4:00 PM - Introduction to Tajweed Rules',
                    '4:30 PM - Makharij (Articulation Points)',
                    '5:00 PM - Sifaat (Characteristics)',
                    '5:30 PM - Practical Recitation Practice',
                    '6:00 PM - Individual Assessment'
                ],
                'contact_info' => [
                    'phone' => '+960 797 2434',
                    'email' => 'quran@akuru.edu.mv'
                ],
                'is_featured' => false,
                'is_public' => true,
                'send_reminders' => true,
                'reminder_days' => 1,
            ],
            [
                'title' => 'Eid Celebration 2024',
                'slug' => 'eid-celebration-2024',
                'description' => 'Join us for a joyous Eid celebration with the Akuru Institute community. Enjoy traditional food, games, and activities for the whole family. This is a special time to come together and celebrate our faith.',
                'short_description' => 'Community Eid celebration with family activities',
                'cover_image' => 'events/eid-celebration.jpg',
                'location' => 'Main Campus Grounds',
                'address' => 'Akuru Institute, Malé, Maldives',
                'start_date' => Carbon::now()->addDays(45)->setTime(10, 0),
                'end_date' => Carbon::now()->addDays(45)->setTime(16, 0),
                'type' => 'celebration',
                'status' => 'published',
                'registration_type' => 'optional',
                'max_attendees' => 500,
                'registration_fee' => 0,
                'registration_deadline' => Carbon::now()->addDays(40),
                'registration_start' => Carbon::now()->addDays(5),
                'registration_instructions' => 'Please register to help us plan for food and activities. All are welcome!',
                'requirements' => [
                    'Bring your family',
                    'Traditional dress encouraged',
                    'Bring a dish to share (optional)'
                ],
                'speakers' => [
                    [
                        'name' => 'Imam Abdullah',
                        'title' => 'Eid Khutbah',
                        'bio' => 'Community Imam and spiritual leader'
                    ]
                ],
                'schedule' => [
                    '10:00 AM - Eid Prayer',
                    '10:30 AM - Eid Khutbah',
                    '11:00 AM - Community Breakfast',
                    '12:00 PM - Children\'s Activities',
                    '2:00 PM - Traditional Games',
                    '3:00 PM - Cultural Performances',
                    '4:00 PM - Closing Remarks'
                ],
                'contact_info' => [
                    'phone' => '+960 797 2434',
                    'email' => 'community@akuru.edu.mv'
                ],
                'is_featured' => true,
                'is_public' => true,
                'send_reminders' => true,
                'reminder_days' => 7,
            ],
            [
                'title' => 'Parent-Teacher Conference',
                'slug' => 'parent-teacher-conference',
                'description' => 'Meet with your child\'s teachers to discuss their progress, challenges, and goals. This is an important opportunity to stay informed about your child\'s Islamic education journey.',
                'short_description' => 'Discuss your child\'s progress with teachers',
                'cover_image' => 'events/parent-teacher.jpg',
                'location' => 'Various Classrooms',
                'address' => 'Akuru Institute, Malé, Maldives',
                'start_date' => Carbon::now()->addDays(10)->setTime(14, 0),
                'end_date' => Carbon::now()->addDays(10)->setTime(18, 0),
                'type' => 'meeting',
                'status' => 'published',
                'registration_type' => 'required',
                'max_attendees' => 100,
                'registration_fee' => 0,
                'registration_deadline' => Carbon::now()->addDays(7),
                'registration_start' => Carbon::now()->addDays(-3),
                'registration_instructions' => 'Please select your preferred time slot when registering.',
                'requirements' => [
                    'Bring your child\'s recent work',
                    'Prepare questions about their progress',
                    'Arrive 10 minutes early'
                ],
                'speakers' => [
                    [
                        'name' => 'All Teachers',
                        'title' => 'Subject Teachers',
                        'bio' => 'Dedicated educators committed to student success'
                    ]
                ],
                'schedule' => [
                    '2:00 PM - Registration and Welcome',
                    '2:15 PM - Quran Teachers Available',
                    '3:00 PM - Arabic Teachers Available',
                    '3:45 PM - Islamic Studies Teachers Available',
                    '4:30 PM - General Discussion',
                    '5:30 PM - Closing'
                ],
                'contact_info' => [
                    'phone' => '+960 797 2434',
                    'email' => 'parents@akuru.edu.mv'
                ],
                'is_featured' => false,
                'is_public' => true,
                'send_reminders' => true,
                'reminder_days' => 2,
            ],
        ];

        foreach ($events as $event) {
            Event::create($event);
        }
    }
}