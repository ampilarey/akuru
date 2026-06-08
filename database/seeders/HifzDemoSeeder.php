<?php

namespace Database\Seeders;

use App\Enums\Hifz\HifzAttendanceStatus;
use App\Enums\Hifz\HifzLaneResult;
use App\Enums\Hifz\HifzMilestoneStatus;
use App\Enums\Hifz\HifzMilestoneType;
use App\Enums\Hifz\HifzMistakeSeverity;
use App\Enums\Hifz\HifzMistakeType;
use App\Enums\Hifz\HifzOverallStatus;
use App\Enums\Hifz\HifzSessionStatus;
use App\Models\ClassRoom;
use App\Models\HifzEnrollment;
use App\Models\HifzMilestone;
use App\Models\HifzMistake;
use App\Models\HifzProgram;
use App\Models\HifzSession;
use App\Models\HifzSessionRecord;
use App\Models\ParentGuardian;
use App\Models\QuranAyah;
use App\Models\QuranMushaf;
use App\Models\QuranWord;
use App\Models\Student;
use App\Models\Surah;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class HifzDemoSeeder extends Seeder
{
    public function run(): void
    {
        $headmaster = User::where('email', 'headmaster@akuru.edu.mv')->first();
        $supervisor = User::where('email', 'supervisor@akuru.edu.mv')->first();
        $teacherUser = User::where('email', 'teacher@akuru.edu.mv')->first();
        $school = \App\Models\School::first();
        $quranClass = ClassRoom::where('level', 'Quran')->first();

        if (! $teacherUser || ! $headmaster) {
            $this->command->warn('Run UserSeeder first.');

            return;
        }

        $teacher = Teacher::firstOrCreate(
            ['user_id' => $teacherUser->id],
            [
                'school_id' => $school?->id,
                'teacher_id' => 'T-HIFZ-001',
                'first_name' => 'Mohamed',
                'last_name' => 'Ali',
                'date_of_birth' => '1985-03-20',
                'gender' => 'male',
                'phone' => '+960 123-4569',
                'email' => 'teacher@akuru.edu.mv',
                'address' => 'Malé',
                'qualification' => 'Islamic Studies',
                'specialization' => 'Quran Memorization',
                'joining_date' => now()->subYears(2),
                'status' => 'active',
            ]
        );

        $studentUser = User::where('email', 'student@akuru.edu.mv')->first();
        if ($studentUser) {
            Student::firstOrCreate(
                ['user_id' => $studentUser->id],
                [
                    'school_id' => $school?->id,
                    'class_id' => $quranClass?->id,
                    'student_id' => 'S-HIFZ-001',
                    'first_name' => 'Ahmed',
                    'last_name' => 'Hassan',
                    'date_of_birth' => '2010-08-10',
                    'gender' => 'male',
                    'admission_date' => now()->subYear(),
                    'status' => 'active',
                ]
            );
        }

        $program = HifzProgram::firstOrCreate(
            ['name' => 'Quran Hifz Program A'],
            [
                'description' => 'Demo Hifz program for Akuru Institute',
                'class_id' => $quranClass?->id,
                'dean_id' => $headmaster->id,
                'supervisor_id' => $supervisor?->id,
                'default_teacher_id' => $teacher->id,
                'status' => 'active',
                'created_by' => $headmaster->id,
            ]
        );

        $students = Student::when($school, fn ($q) => $q->where('school_id', $school->id))->take(1)->get();
        foreach ($students as $i => $student) {
            HifzEnrollment::firstOrCreate(
                ['hifz_program_id' => $program->id, 'student_id' => $student->id],
                [
                    'teacher_id' => $teacher->id,
                    'supervisor_id' => $supervisor?->id,
                    'start_date' => now()->subMonths(2),
                    'current_juz' => 1,
                    'current_page' => 5 + $i,
                    'status' => 'active',
                ]
            );
        }

        $surah = Surah::where('index', 1)->first();

        for ($day = 4; $day >= 0; $day--) {
            $date = Carbon::today()->subDays($day);
            $session = HifzSession::firstOrCreate(
                [
                    'hifz_program_id' => $program->id,
                    'teacher_id' => $teacher->id,
                    'session_date' => $date->toDateString(),
                ],
                [
                    'supervisor_id' => $supervisor?->id,
                    'status' => $day === 0 ? HifzSessionStatus::Draft : HifzSessionStatus::Completed,
                    'created_by' => $teacherUser->id,
                ]
            );

            foreach ($students as $student) {
                $record = HifzSessionRecord::firstOrCreate(
                    ['hifz_session_id' => $session->id, 'student_id' => $student->id],
                    [
                        'hifz_program_id' => $program->id,
                        'teacher_id' => $teacher->id,
                        'supervisor_id' => $supervisor?->id,
                        'attendance_status' => HifzAttendanceStatus::Present,
                        'new_from_surah_id' => $surah?->id,
                        'new_from_ayah' => 1,
                        'new_to_ayah' => 5,
                        'new_result' => HifzLaneResult::Pass,
                        'new_score' => 85,
                        'recent_revision_text' => 'Yesterday page',
                        'old_revision_text' => 'Juz 1 review',
                        'mistake_count' => $day === 0 ? 2 : 0,
                        'haraka_mistakes' => $day === 0 ? 1 : 0,
                        'overall_status' => $day === 0 ? HifzOverallStatus::Good : HifzOverallStatus::Excellent,
                        'parent_visible_note' => 'Good effort today.',
                        'next_target' => 'Continue Surah Al-Fatiha',
                        'requires_supervisor_review' => $day === 0,
                        'created_by' => $teacherUser->id,
                    ]
                );

                if ($day === 0 && $record->mistakes()->count() === 0) {
                    HifzMistake::create([
                        'hifz_session_record_id' => $record->id,
                        'student_id' => $student->id,
                        'teacher_id' => $teacher->id,
                        'surah_number' => 1,
                        'ayah_number' => 2,
                        'word_number' => 3,
                        'mistake_type' => HifzMistakeType::Haraka,
                        'severity' => HifzMistakeSeverity::Minor,
                        'teacher_note' => 'Fatha read as kasra',
                    ]);
                }
            }
        }

        $mushaf = QuranMushaf::firstOrCreate(
            ['name' => 'Demo Madani Mushaf'],
            ['source_type' => 'manual', 'page_count' => 604, 'is_active' => true, 'created_by' => $headmaster->id]
        );

        $ayah = QuranAyah::firstOrCreate(
            ['quran_mushaf_id' => $mushaf->id, 'surah_number' => 1, 'ayah_number' => 1],
            ['text_uthmani' => 'بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ', 'page_number' => 1]
        );

        $words = ['بِسْمِ', 'اللَّهِ', 'الرَّحْمَٰنِ', 'الرَّحِيمِ'];
        foreach ($words as $i => $text) {
            QuranWord::firstOrCreate(
                ['quran_mushaf_id' => $mushaf->id, 'surah_number' => 1, 'ayah_number' => 1, 'word_number' => $i + 1],
                ['quran_ayah_id' => $ayah->id, 'word_text' => $text, 'page_number' => 1]
            );
        }

        HifzMilestone::firstOrCreate(
            [
                'hifz_program_id' => $program->id,
                'student_id' => $students->first()->id,
                'type' => HifzMilestoneType::SurahCompleted,
                'surah_number' => 1,
            ],
            [
                'teacher_id' => $teacher->id,
                'completed_at' => now(),
                'recommended_by' => $teacherUser->id,
                'recommended_at' => now(),
                'status' => HifzMilestoneStatus::Pending,
                'title' => 'Surah Al-Fatiha completed',
                'created_by' => $teacherUser->id,
            ]
        );

        $parentUser = User::where('email', 'parent@akuru.edu.mv')->first();
        if ($parentUser && $students->isNotEmpty()) {
            $guardian = ParentGuardian::firstOrCreate(
                ['user_id' => $parentUser->id],
                [
                    'first_name' => 'Hassan',
                    'last_name' => 'Ahmed',
                    'phone' => '+960 123-4571',
                    'email' => 'parent@akuru.edu.mv',
                    'address' => 'Malé',
                    'relationship' => 'father',
                ]
            );
            $guardian->students()->syncWithoutDetaching([
                $students->first()->id => ['relationship' => 'father', 'is_primary_contact' => true],
            ]);
        }
    }
}
