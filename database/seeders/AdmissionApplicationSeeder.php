<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdmissionApplication;
use Carbon\Carbon;

class AdmissionApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $applications = [
            [
                'application_number' => 'AKU2024001',
                'student_name' => 'Ahmed Hassan Ali',
                'date_of_birth' => Carbon::parse('2010-05-15'),
                'gender' => 'male',
                'nationality' => 'Maldivian',
                'parent_name' => 'Hassan Ali',
                'parent_phone' => '+960 123-4567',
                'parent_email' => 'hassan.ali@email.com',
                'address' => 'Hulhumale, Maldives',
                'previous_school' => 'Malé International School',
                'grade_applying_for' => 'Quran Memorization (Hifz)',
                'status' => 'new',
                'priority' => 'medium',
                'source' => 'website',
                'notes' => 'My son is very interested in learning the Quran and has shown great dedication.',
                'documents' => json_encode(['birth_certificate', 'school_report', 'photo']),
                'created_at' => Carbon::now()->subDays(3),
            ],
            [
                'application_number' => 'AKU2024002',
                'student_name' => 'Fatima Mohamed Ibrahim',
                'date_of_birth' => Carbon::parse('2008-08-22'),
                'gender' => 'female',
                'nationality' => 'Maldivian',
                'parent_name' => 'Mohamed Ibrahim',
                'parent_phone' => '+960 456-7890',
                'parent_email' => 'mohamed.ibrahim@email.com',
                'address' => 'Malé, Maldives',
                'previous_school' => 'Malé School',
                'grade_applying_for' => 'Arabic Language',
                'status' => 'reviewed',
                'priority' => 'medium',
                'source' => 'website',
                'notes' => 'I want to learn Arabic to better understand the Quran and Islamic texts.',
                'documents' => json_encode(['birth_certificate', 'school_report', 'photo']),
                'created_at' => Carbon::now()->subDays(5),
            ],
            [
                'application_number' => 'AKU2024003',
                'student_name' => 'Omar Hassan Ali',
                'date_of_birth' => Carbon::parse('2012-03-10'),
                'gender' => 'male',
                'nationality' => 'Maldivian',
                'parent_name' => 'Hassan Ali',
                'parent_phone' => '+960 789-0123',
                'parent_email' => 'hassan.ali@email.com',
                'address' => 'Addu City, Maldives',
                'previous_school' => 'Addu School',
                'grade_applying_for' => 'Islamic Studies',
                'status' => 'interview_scheduled',
                'priority' => 'high',
                'source' => 'website',
                'notes' => 'My son is eager to learn about Islam and start his Islamic education journey.',
                'documents' => json_encode(['birth_certificate', 'school_report', 'photo']),
                'timeline' => json_encode([
                    'application_received' => Carbon::now()->subDays(7)->toDateString(),
                    'review_completed' => Carbon::now()->subDays(5)->toDateString(),
                    'interview_scheduled' => Carbon::now()->addDays(2)->toDateString(),
                ]),
                'created_at' => Carbon::now()->subDays(7),
            ],
            [
                'application_number' => 'AKU2024004',
                'student_name' => 'Aisha Mohamed Hassan',
                'date_of_birth' => Carbon::parse('2009-11-18'),
                'gender' => 'female',
                'nationality' => 'Maldivian',
                'parent_name' => 'Mohamed Hassan',
                'parent_phone' => '+960 012-3456',
                'parent_email' => 'mohamed.hassan@email.com',
                'address' => 'Malé, Maldives',
                'previous_school' => 'Malé School',
                'grade_applying_for' => 'Quran Memorization (Hifz)',
                'status' => 'accepted',
                'priority' => 'high',
                'source' => 'website',
                'notes' => 'I have been learning Quran for 2 years and want to continue with proper guidance.',
                'documents' => json_encode(['birth_certificate', 'school_report', 'photo']),
                'timeline' => json_encode([
                    'application_received' => Carbon::now()->subDays(10)->toDateString(),
                    'review_completed' => Carbon::now()->subDays(8)->toDateString(),
                    'interview_completed' => Carbon::now()->subDays(5)->toDateString(),
                    'accepted' => Carbon::now()->subDays(2)->toDateString(),
                ]),
                'expected_start' => Carbon::now()->addDays(30),
                'created_at' => Carbon::now()->subDays(10),
            ],
            [
                'application_number' => 'AKU2024005',
                'student_name' => 'Yusuf Ibrahim Ali',
                'date_of_birth' => Carbon::parse('2011-07-05'),
                'gender' => 'male',
                'nationality' => 'Maldivian',
                'parent_name' => 'Ibrahim Ali',
                'parent_phone' => '+960 345-6789',
                'parent_email' => 'ibrahim.ali@email.com',
                'address' => 'Malé, Maldives',
                'previous_school' => 'Malé School',
                'grade_applying_for' => 'Arabic Language',
                'status' => 'rejected',
                'priority' => 'medium',
                'source' => 'website',
                'notes' => 'I want to learn Arabic to better understand the Quran.',
                'documents' => json_encode(['birth_certificate', 'school_report', 'photo']),
                'timeline' => json_encode([
                    'application_received' => Carbon::now()->subDays(8)->toDateString(),
                    'review_completed' => Carbon::now()->subDays(6)->toDateString(),
                    'interview_completed' => Carbon::now()->subDays(4)->toDateString(),
                    'rejected' => Carbon::now()->subDays(2)->toDateString(),
                ]),
                'created_at' => Carbon::now()->subDays(8),
            ],
        ];

        foreach ($applications as $application) {
            AdmissionApplication::create($application);
        }
    }
}