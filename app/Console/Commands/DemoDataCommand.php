<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\ClassRoom;
use App\Models\Subject;
use App\Models\QuranProgress;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Surah;
use App\Models\School;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class DemoDataCommand extends Command
{
    protected $signature = 'demo:generate {--fresh : Fresh database with demo data}';
    protected $description = 'Generate demo data for Akuru LMS';

    public function handle()
    {
        $this->info('🚀 Generating demo data for Akuru LMS...');

        if ($this->option('fresh')) {
            $this->info('🔄 Fresh mode: Clearing existing data...');
            $this->call('migrate:fresh');
            $this->call('db:seed', ['--class' => 'RoleSeeder']);
        }

        $this->createDemoSchool();
        $this->createDemoUsers();
        $this->createDemoClasses();
        $this->createDemoSubjects();
        $this->createDemoStudents();
        $this->createDemoTeachers();
        $this->createDemoQuranProgress();
        $this->createDemoAnnouncements();
        $this->createDemoAssignments();

        $this->info('✅ Demo data generated successfully!');
        $this->displayDemoAccounts();
    }

    private function createDemoSchool()
    {
        $this->info('🏫 Creating demo school...');

        School::create([
            'name' => 'Akuru Institute',
            'name_arabic' => 'معهد أكورو',
            'name_dhivehi' => 'އަކުރު އިންސްޓިޓުއުޓް',
            'address' => 'Malé, Maldives',
            'phone' => '+960 123-4567',
            'email' => 'info@akuru.edu',
            'website' => 'https://akuru.edu',
            'established_year' => 2020,
            'principal_name' => 'Dr. Ahmed Ali',
            'principal_name_arabic' => 'د. أحمد علي',
            'principal_name_dhivehi' => 'ޑް. އަހްމަދު ޢަލީ',
            'is_active' => true,
        ]);
    }

    private function createDemoUsers()
    {
        $this->info('👥 Creating demo users...');

        // Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@akuru.edu'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
            ]
        );
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // Headmaster user
        $headmaster = User::firstOrCreate(
            ['email' => 'headmaster@akuru.edu'],
            [
                'name' => 'Headmaster',
                'password' => Hash::make('password'),
            ]
        );
        if (!$headmaster->hasRole('headmaster')) {
            $headmaster->assignRole('headmaster');
        }

        // Supervisor user
        $supervisor = User::firstOrCreate(
            ['email' => 'supervisor@akuru.edu'],
            [
                'name' => 'Supervisor',
                'password' => Hash::make('password'),
            ]
        );
        if (!$supervisor->hasRole('supervisor')) {
            $supervisor->assignRole('supervisor');
        }
    }

    private function createDemoClasses()
    {
        $this->info('🏫 Creating demo classes...');

        $school = School::first();
        $classes = [
            ['name' => 'Grade 1A', 'name_arabic' => 'الصف الأول أ', 'name_dhivehi' => 'ގްރޭޑު 1A', 'level' => 1, 'capacity' => 25],
            ['name' => 'Grade 1B', 'name_arabic' => 'الصف الأول ب', 'name_dhivehi' => 'ގްރޭޑު 1B', 'level' => 1, 'capacity' => 25],
            ['name' => 'Grade 2A', 'name_arabic' => 'الصف الثاني أ', 'name_dhivehi' => 'ގްރޭޑު 2A', 'level' => 2, 'capacity' => 25],
            ['name' => 'Grade 2B', 'name_arabic' => 'الصف الثاني ب', 'name_dhivehi' => 'ގްރޭޑު 2B', 'level' => 2, 'capacity' => 25],
            ['name' => 'Quran Beginners', 'name_arabic' => 'مبتدئين القرآن', 'name_dhivehi' => 'ކުރާން ބެގިންނަރުތައް', 'level' => 1, 'capacity' => 20],
            ['name' => 'Quran Intermediate', 'name_arabic' => 'متوسطين القرآن', 'name_dhivehi' => 'ކުރާން މިޑިއަމް', 'level' => 2, 'capacity' => 20],
        ];

        foreach ($classes as $classData) {
            $classData['school_id'] = $school->id;
            $classData['is_active'] = true;
            ClassRoom::firstOrCreate(
                ['name' => $classData['name'], 'school_id' => $school->id],
                $classData
            );
        }
    }

    private function createDemoSubjects()
    {
        $this->info('📚 Creating demo subjects...');

        $school = School::first();
        $subjects = [
            ['name' => 'Quran Recitation', 'name_arabic' => 'تلاوة القرآن', 'name_dhivehi' => 'ކުރާން ތަލާވާ', 'code' => 'QUR001', 'type' => 'Quran', 'is_quran_subject' => true, 'is_active' => true],
            ['name' => 'Quran Memorization', 'name_arabic' => 'حفظ القرآن', 'name_dhivehi' => 'ކުރާން ހަފްޒު', 'code' => 'QUR002', 'type' => 'Quran', 'is_quran_subject' => true, 'is_active' => true],
            ['name' => 'Arabic Language', 'name_arabic' => 'اللغة العربية', 'name_dhivehi' => 'ޢަރަބި ބަހުން', 'code' => 'ARA001', 'type' => 'Arabic', 'is_quran_subject' => false, 'is_active' => true],
            ['name' => 'Islamic Studies', 'name_arabic' => 'الدراسات الإسلامية', 'name_dhivehi' => 'އިސްލާމީ ތައްލީމް', 'code' => 'ISL001', 'type' => 'Islamic Studies', 'is_quran_subject' => false, 'is_active' => true],
            ['name' => 'Tajweed', 'name_arabic' => 'التجويد', 'name_dhivehi' => 'ތަޖްވީދު', 'code' => 'TAJ001', 'type' => 'Quran', 'is_quran_subject' => true, 'is_active' => true],
        ];

        foreach ($subjects as $subjectData) {
            $subjectData['school_id'] = $school->id;
            Subject::firstOrCreate(
                ['name' => $subjectData['name'], 'school_id' => $school->id],
                $subjectData
            );
        }
    }

    private function createDemoStudents()
    {
        $this->info('🎓 Creating demo students...');

        $classes = ClassRoom::all();
        $school = School::first();

        for ($i = 1; $i <= 20; $i++) {
            $user = User::firstOrCreate(
                ['email' => "student$i@akuru.edu"],
                [
                    'name' => "Student $i",
                    'password' => Hash::make('password'),
                ]
            );
            if (!$user->hasRole('student')) {
                $user->assignRole('student');
            }

            $student = Student::firstOrCreate(
                ['student_id' => "STU" . str_pad($i, 3, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $user->id,
                    'school_id' => $school->id,
                    'first_name' => "Student",
                    'last_name' => $i,
                    'full_name' => "Student $i",
                    'class_id' => $classes->random()->id,
                    'date_of_birth' => now()->subYears(rand(6, 18)),
                    'admission_date' => now()->subMonths(rand(1, 12)),
                    'phone' => '+960' . rand(7000000, 7999999),
                    'address' => 'Malé, Maldives',
                ]
            );
        }
    }

    private function createDemoTeachers()
    {
        $this->info('👨‍🏫 Creating demo teachers...');

        $school = School::first();

        for ($i = 1; $i <= 10; $i++) {
            $user = User::firstOrCreate(
                ['email' => "teacher$i@akuru.edu"],
                [
                    'name' => "Teacher $i",
                    'password' => Hash::make('password'),
                ]
            );
            if (!$user->hasRole('teacher')) {
                $user->assignRole('teacher');
            }

            Teacher::firstOrCreate(
                ['teacher_id' => "TCH" . str_pad($i, 3, '0', STR_PAD_LEFT)],
                [
                    'user_id' => $user->id,
                    'school_id' => $school->id,
                    'first_name' => "Teacher",
                    'last_name' => $i,
                    'full_name' => "Teacher $i",
                    'email' => "teacher$i@akuru.edu",
                    'date_of_birth' => now()->subYears(rand(25, 50)),
                    'phone' => '+960' . rand(7000000, 7999999),
                    'address' => 'Malé, Maldives',
                    'qualification' => 'Islamic Studies',
                    'specialization' => 'Quran Studies',
                    'joining_date' => now()->subMonths(rand(1, 24)),
                    'experience_years' => rand(1, 15),
                ]
            );
        }
    }

    private function createDemoQuranProgress()
    {
        $this->info('📖 Creating demo Quran progress...');

        $students = Student::all();
        $teachers = Teacher::all();
        $surahs = Surah::take(10)->get();

        foreach ($students as $student) {
            foreach ($surahs->random(rand(2, 5)) as $surah) {
                QuranProgress::create([
                    'student_id' => $student->id,
                    'teacher_id' => $teachers->random()->id,
                    'surah_number' => $surah->index,
                    'surah_name' => $surah->english_name,
                    'surah_name_arabic' => $surah->arabic_name,
                    'from_ayah' => 1,
                    'to_ayah' => rand(1, $surah->ayah_count),
                    'type' => ['memorization', 'recitation', 'revision'][rand(0, 2)],
                    'status' => ['completed', 'in_progress', 'needs_revision'][rand(0, 2)],
                    'accuracy_percentage' => rand(70, 100),
                    'teacher_notes' => 'Demo progress entry',
                ]);
            }
        }
    }

    private function createDemoAnnouncements()
    {
        $this->info('📢 Creating demo announcements...');

        $school = School::first();
        $announcements = [
            [
                'title' => 'Welcome to New Academic Year',
                'title_arabic' => 'مرحباً بالعام الأكاديمي الجديد',
                'title_dhivehi' => 'އެކެޑެމިކް އަހަރުގެ ހުށަހަޅާ',
                'content' => 'We welcome all students and parents to the new academic year. Classes will begin on Monday.',
                'content_arabic' => 'نرحب بجميع الطلاب وأولياء الأمور في العام الأكاديمي الجديد. ستبدأ الفصول يوم الاثنين.',
                'content_dhivehi' => 'ތައްލިމުން އާ ގާޑިއަނުތަކަށް އެކެޑެމިކް އަހަރުގެ ހުށަހަޅާ. ހަސް ފެށުމުގެ ދުވަހު އެންމެ ހުށަހަޅާ.',
                'is_important' => true,
            ],
            [
                'title' => 'Quran Competition',
                'title_arabic' => 'مسابقة القرآن',
                'title_dhivehi' => 'ކުރާން ކޮމްޕެޓިޝަން',
                'content' => 'Annual Quran recitation competition will be held next month. Registration is now open.',
                'content_arabic' => 'ستقام مسابقة تلاوة القرآن السنوية الشهر المقبل. التسجيل مفتوح الآن.',
                'content_dhivehi' => 'އަހަރުގެ ކުރާން ތަލާވާ ކޮމްޕެޓިޝަން ފެށުމުގެ ދުވަހު އެންމެ ހުށަހަޅާ. ރެޖިސްޓްރޭޝަން ހުށަހަޅާ.',
                'is_important' => false,
            ],
        ];

        $admin = User::where('email', 'admin@akuru.edu')->first();
        foreach ($announcements as $announcementData) {
            $announcementData['school_id'] = $school->id;
            $announcementData['created_by'] = $admin->id;
            $announcementData['publish_date'] = now();
            Announcement::create($announcementData);
        }
    }

    private function createDemoAssignments()
    {
        $this->info('📝 Creating demo assignments...');

        $teachers = Teacher::all();
        $classes = ClassRoom::all();
        $subjects = Subject::all();

        for ($i = 1; $i <= 15; $i++) {
            Assignment::create([
                'title' => "Assignment $i",
                'title_arabic' => "الواجب $i",
                'title_dhivehi' => "ވެއްޖެ $i",
                'description' => "This is a demo assignment for testing purposes.",
                'description_arabic' => "هذا واجب تجريبي لأغراض الاختبار.",
                'description_dhivehi' => "މި ވެއްޖެ ޓެސްޓް ކުރުމަށް ހުށަހަޅާ.",
                'instructions' => "Please complete this assignment and submit by the due date.",
                'class_id' => $classes->random()->id,
                'subject_id' => $subjects->random()->id,
                'teacher_id' => $teachers->random()->id,
                'due_date' => now()->addDays(rand(1, 30)),
                'max_grade' => 100,
            ]);
        }
    }

    private function displayDemoAccounts()
    {
        $this->info('');
        $this->info('🎉 Demo accounts created:');
        $this->info('');
        $this->info('👑 Admin Account:');
        $this->info('   Email: admin@akuru.edu');
        $this->info('   Password: password');
        $this->info('');
        $this->info('🎓 Headmaster Account:');
        $this->info('   Email: headmaster@akuru.edu');
        $this->info('   Password: password');
        $this->info('');
        $this->info('👨‍🏫 Teacher Accounts:');
        $this->info('   Email: teacher1@akuru.edu to teacher10@akuru.edu');
        $this->info('   Password: password');
        $this->info('');
        $this->info('🎓 Student Accounts:');
        $this->info('   Email: student1@akuru.edu to student20@akuru.edu');
        $this->info('   Password: password');
        $this->info('');
        $this->info('🌐 Access the application at: http://localhost:8000');
    }
}