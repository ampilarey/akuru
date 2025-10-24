<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{ContactInquiry, InquiryType};
use Carbon\Carbon;

class ContactInquirySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get inquiry types
        $generalInquiry = InquiryType::where('slug', 'general-inquiry')->first();
        $admissionsInquiry = InquiryType::where('slug', 'admissions-inquiry')->first();
        $courseInquiry = InquiryType::where('slug', 'course-information')->first();
        $eventInquiry = InquiryType::where('slug', 'event-information')->first();
        $technicalSupport = InquiryType::where('slug', 'technical-support')->first();
        $feedback = InquiryType::where('slug', 'feedback-suggestions')->first();

        $inquiries = [
            [
                'inquiry_type_id' => $admissionsInquiry->id,
                'name' => 'Ahmed Hassan',
                'email' => 'ahmed.hassan@email.com',
                'phone' => '+960 123-4567',
                'subject' => 'Admission for Quran Memorization Program',
                'message' => 'Assalamu Alaikum, I am interested in enrolling my 12-year-old son in the Quran memorization program. Could you please provide information about the admission requirements, schedule, and fees?',
                'status' => 'new',
                'priority' => 'medium',
                'custom_fields' => [
                    'interested_program' => 'Quran Memorization (Hifz)',
                    'age_group' => 'Kids (6-12 years)',
                    'previous_experience' => 'He has completed basic Arabic reading and knows a few short surahs by heart.'
                ],
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'created_at' => Carbon::now()->subDays(2),
            ],
            [
                'inquiry_type_id' => $courseInquiry->id,
                'name' => 'Fatima Ali',
                'email' => 'fatima.ali@email.com',
                'phone' => '+960 234-5678',
                'subject' => 'Arabic Language Course Schedule',
                'message' => 'I would like to know about the Arabic language course schedule for adults. What are the different levels available and when do the classes start?',
                'status' => 'in_progress',
                'priority' => 'medium',
                'admin_notes' => 'Sent course catalog and schedule. Waiting for response about preferred level.',
                'custom_fields' => [
                    'course_name' => 'Arabic Language',
                    'preferred_schedule' => 'Evening (6:00 PM - 9:00 PM)'
                ],
                'ip_address' => '192.168.1.101',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'created_at' => Carbon::now()->subDays(5),
            ],
            [
                'inquiry_type_id' => $eventInquiry->id,
                'name' => 'Mohamed Ibrahim',
                'email' => 'mohamed.ibrahim@email.com',
                'phone' => '+960 345-6789',
                'subject' => 'Quran Competition Registration',
                'message' => 'I would like to register my daughter for the upcoming Quran competition. What are the requirements and how can I register?',
                'status' => 'resolved',
                'priority' => 'high',
                'admin_notes' => 'Registration completed. Student assigned to Youth category.',
                'response' => 'Thank you for your interest in the Quran competition. Your daughter has been successfully registered in the Youth category (ages 13-18). The competition will be held on December 20, 2024, at 9:00 AM. Please arrive 30 minutes early with your Quran.',
                'responded_at' => Carbon::now()->subDays(1),
                'custom_fields' => [
                    'event_name' => 'Annual Quran Competition 2024',
                    'registration_interest' => ['Yes, I would like to register for this event']
                ],
                'ip_address' => '192.168.1.102',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15',
                'created_at' => Carbon::now()->subDays(7),
            ],
            [
                'inquiry_type_id' => $generalInquiry->id,
                'name' => 'Aisha Mohamed',
                'email' => 'aisha.mohamed@email.com',
                'phone' => '+960 456-7890',
                'subject' => 'Institute Location and Contact Information',
                'message' => 'Could you please provide the complete address and contact information for Akuru Institute? I would like to visit the campus.',
                'status' => 'resolved',
                'priority' => 'low',
                'admin_notes' => 'Provided complete address and visiting hours.',
                'response' => 'Thank you for your interest in visiting Akuru Institute. Our address is: Akuru Institute, Malé, Maldives. Phone: +960 797 2434, Email: info@akuru.edu.mv. Visiting hours: Sunday to Thursday, 8:00 AM - 5:00 PM. We look forward to welcoming you!',
                'responded_at' => Carbon::now()->subDays(3),
                'ip_address' => '192.168.1.103',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'created_at' => Carbon::now()->subDays(4),
            ],
            [
                'inquiry_type_id' => $technicalSupport->id,
                'name' => 'Hassan Ali',
                'email' => 'hassan.ali@email.com',
                'phone' => '+960 567-8901',
                'subject' => 'Website Login Issues',
                'message' => 'I am unable to log into my account on the website. I keep getting an error message when I try to reset my password.',
                'status' => 'in_progress',
                'priority' => 'high',
                'admin_notes' => 'Password reset issue. Sent reset link via email. User should check spam folder.',
                'custom_fields' => [
                    'issue_type' => 'Login problems',
                    'browser_info' => 'Chrome on Windows 10'
                ],
                'ip_address' => '192.168.1.104',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'created_at' => Carbon::now()->subDays(1),
            ],
            [
                'inquiry_type_id' => $feedback->id,
                'name' => 'Ibrahim Hassan',
                'email' => 'ibrahim.hassan@email.com',
                'phone' => '+960 678-9012',
                'subject' => 'Excellent Teaching Quality',
                'message' => 'I wanted to express my appreciation for the excellent teaching quality at Akuru Institute. My children have shown remarkable progress in their Quran studies.',
                'status' => 'resolved',
                'priority' => 'low',
                'admin_notes' => 'Positive feedback forwarded to teaching staff.',
                'response' => 'Thank you for your wonderful feedback! We are delighted to hear about your children\'s progress. Your appreciation means a lot to our teaching staff and motivates us to continue providing quality education.',
                'responded_at' => Carbon::now()->subDays(2),
                'custom_fields' => [
                    'feedback_category' => 'Teaching Quality',
                    'rating' => 'Excellent'
                ],
                'ip_address' => '192.168.1.105',
                'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'created_at' => Carbon::now()->subDays(3),
            ],
            [
                'inquiry_type_id' => $admissionsInquiry->id,
                'name' => 'Maryam Ahmed',
                'email' => 'maryam.ahmed@email.com',
                'phone' => '+960 789-0123',
                'subject' => 'Adult Education Program Inquiry',
                'message' => 'I am interested in joining the adult education program. I have no prior Arabic knowledge but am very motivated to learn. What would be the best starting point?',
                'status' => 'new',
                'priority' => 'medium',
                'custom_fields' => [
                    'interested_program' => 'Arabic Language',
                    'age_group' => 'Adult (19+ years)',
                    'previous_experience' => 'No prior Arabic knowledge, but very motivated to learn.'
                ],
                'ip_address' => '192.168.1.106',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15',
                'created_at' => Carbon::now()->subHours(6),
            ],
            [
                'inquiry_type_id' => $courseInquiry->id,
                'name' => 'Omar Hassan',
                'email' => 'omar.hassan@email.com',
                'phone' => '+960 890-1234',
                'subject' => 'Tajweed Masterclass Registration',
                'message' => 'I would like to register for the upcoming Tajweed masterclass. Is there still space available and what are the requirements?',
                'status' => 'resolved',
                'priority' => 'medium',
                'admin_notes' => 'Registration completed. Student has basic Quran reading ability.',
                'response' => 'Thank you for your interest in the Tajweed masterclass. You have been successfully registered! The class will be held on [date] from 4:00 PM to 6:00 PM. Please bring your own Quran and notebook. We look forward to seeing you there!',
                'responded_at' => Carbon::now()->subDays(1),
                'custom_fields' => [
                    'course_name' => 'Tajweed Masterclass',
                    'preferred_schedule' => 'Afternoon (2:00 PM - 5:00 PM)'
                ],
                'ip_address' => '192.168.1.107',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'created_at' => Carbon::now()->subDays(4),
            ],
            [
                'inquiry_type_id' => $generalInquiry->id,
                'name' => 'Khadija Ali',
                'email' => 'khadija.ali@email.com',
                'phone' => '+960 901-2345',
                'subject' => 'Prayer Times and Facilities',
                'message' => 'Does Akuru Institute have prayer facilities for students and visitors? What are the prayer times?',
                'status' => 'new',
                'priority' => 'low',
                'ip_address' => '192.168.1.108',
                'user_agent' => 'Mozilla/5.0 (Android 11; Mobile; rv:68.0) Gecko/68.0 Firefox/88.0',
                'created_at' => Carbon::now()->subHours(2),
            ],
            [
                'inquiry_type_id' => $technicalSupport->id,
                'name' => 'Yusuf Ibrahim',
                'email' => 'yusuf.ibrahim@email.com',
                'phone' => '+960 012-3456',
                'subject' => 'Mobile App Not Working',
                'message' => 'The mobile app is not loading properly on my Android device. It keeps crashing when I try to access the course materials.',
                'status' => 'in_progress',
                'priority' => 'high',
                'admin_notes' => 'Android app crash issue. Investigating compatibility with latest Android version.',
                'custom_fields' => [
                    'issue_type' => 'Mobile app problems',
                    'browser_info' => 'Android 12, Samsung Galaxy S21'
                ],
                'ip_address' => '192.168.1.109',
                'user_agent' => 'Mozilla/5.0 (Linux; Android 12; SM-G991B) AppleWebKit/537.36',
                'created_at' => Carbon::now()->subHours(12),
            ],
        ];

        foreach ($inquiries as $inquiry) {
            ContactInquiry::create($inquiry);
        }
    }
}