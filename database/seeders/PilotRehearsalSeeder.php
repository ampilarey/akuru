<?php

namespace Database\Seeders;

use App\Domains\Academics\Actions\ActivateAcademicYearAction;
use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Enums\AcademicYearStatus;
use App\Domains\Academics\Enums\RoomType;
use App\Domains\Academics\Enums\TermStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\ClassRoom;
use App\Domains\Academics\Models\CoursePlan;
use App\Domains\Academics\Models\Period;
use App\Domains\Academics\Models\PlanTopic;
use App\Domains\Academics\Models\Room;
use App\Domains\Academics\Models\Subject;
use App\Domains\Academics\Models\Term;
use App\Domains\Academics\Models\Timetable;
use App\Domains\Finance\Actions\SaveFeeItemAction;
use App\Domains\Finance\Actions\SaveFeeStructureAction;
use App\Domains\Finance\Enums\FeeFrequency;
use App\Domains\Finance\Enums\FeeItemType;
use App\Domains\Finance\Enums\FeeStructureAppliesTo;
use App\Domains\Finance\Enums\FeeStructureStatus;
use App\Domains\Finance\Models\FeeItem;
use App\Domains\Finance\Models\FeeStructure;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\Student;
use App\Domains\People\Models\Teacher;
use App\Domains\Settings\Models\School;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Single-class pilot rehearsal dataset. Not in DatabaseSeeder.
 *
 * Operator (staging): php artisan db:seed --class=PilotRehearsalSeeder
 *
 * Identity mess follows ADR-021 representative patterns: duplicate national IDs,
 * blank/placeholder IDs, a shared name with different DOBs, and a genuine
 * name+DOB duplicate pair. This is not a sanitised happy path.
 */
class PilotRehearsalSeeder extends Seeder
{
    public const YEAR_NAME = '2026-2027 Pilot';

    public const CLASS_NAME = 'Grade 5';

    public const CLASS_SECTION = 'A';

    public const PARENT_EMAIL = 'parent@akuru.edu.mv';

    public const CLASS_TEACHER_EMAIL = 'teacher@akuru.edu.mv';

    public function run(): void
    {
        $school = School::query()->first();
        if ($school === null) {
            $this->command?->error('No school row. Run DatabaseSeeder first.');

            return;
        }

        $this->call(PeriodSeeder::class);

        $arabic = Subject::query()->where('code', 'ARB101')->firstOrFail();
        $quran = Subject::query()->where('code', 'QUR102')->firstOrFail();
        $islam = Subject::query()->where('code', 'ISL101')->firstOrFail();

        $classTeacher = $this->teacher(
            $school->id,
            self::CLASS_TEACHER_EMAIL,
            'Ustadh Mohamed',
            'Ali',
            'T-PIL-AR',
            'Arabic',
        );
        $quranTeacher = $this->teacher(
            $school->id,
            'teacher.quran@akuru.edu.mv',
            'Ustadha Aishath',
            'Shifa',
            'T-PIL-QR',
            'Quran',
        );
        $islamTeacher = $this->teacher(
            $school->id,
            'teacher.islam@akuru.edu.mv',
            'Ustadh Ibrahim',
            'Naseer',
            'T-PIL-IS',
            'Islamic Studies',
        );

        $year = AcademicYear::query()->firstOrCreate(
            ['name' => self::YEAR_NAME],
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'is_current' => false,
                'status' => AcademicYearStatus::Upcoming,
                'description' => 'Pilot rehearsal academic year',
            ],
        );

        if ($year->status !== AcademicYearStatus::Active) {
            AcademicYear::query()
                ->where('id', '!=', $year->id)
                ->where('status', AcademicYearStatus::Active)
                ->update(['status' => AcademicYearStatus::Closed->value, 'is_current' => false]);
            app(ActivateAcademicYearAction::class)->execute($year->fresh());
            $year = $year->fresh();
        }

        $term = Term::query()->firstOrCreate(
            ['academic_year_id' => $year->id, 'name' => 'Term 1'],
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-06-30',
                'status' => TermStatus::Active,
                'sort_order' => 1,
            ],
        );

        $class = ClassRoom::query()->firstOrCreate(
            [
                'academic_year_id' => $year->id,
                'name' => self::CLASS_NAME,
                'section' => self::CLASS_SECTION,
            ],
            [
                'school_id' => $school->id,
                'level' => 'Primary',
                'capacity' => 20,
                'class_teacher_id' => $classTeacher->user_id,
                'is_active' => true,
                'description' => 'Pilot rehearsal class',
            ],
        );
        $class->forceFill(['class_teacher_id' => $classTeacher->user_id])->save();

        $room = Room::query()->firstOrCreate(
            ['name' => 'Pilot Room 1'],
            [
                'type' => RoomType::Classroom,
                'capacity' => 20,
                'bookable' => true,
                'active' => true,
            ],
        );

        $period1 = Period::query()->where('school_id', $school->id)->where('order', 2)->firstOrFail();
        $period2 = Period::query()->where('school_id', $school->id)->where('order', 3)->firstOrFail();
        $period3 = Period::query()->where('school_id', $school->id)->where('order', 5)->firstOrFail();

        $slots = [
            [$period1, $arabic, $classTeacher],
            [$period2, $quran, $quranTeacher],
            [$period3, $islam, $islamTeacher],
        ];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $day) {
            foreach ($slots as [$period, $subject, $teacher]) {
                Timetable::query()->firstOrCreate(
                    [
                        'class_id' => $class->id,
                        'academic_year_id' => $year->id,
                        'day_of_week' => $day,
                        'period_id' => $period->id,
                    ],
                    [
                        'subject_id' => $subject->id,
                        'teacher_id' => $teacher->id,
                        'term_id' => $term->id,
                        'room_id' => $room->id,
                        'room' => $room->name,
                        'start_time' => $period->start_time?->format('H:i:s'),
                        'end_time' => $period->end_time?->format('H:i:s'),
                        'is_active' => true,
                    ],
                );
            }
        }

        $plan = CoursePlan::query()->firstOrCreate(
            [
                'teacher_id' => $classTeacher->id,
                'subject_id' => $arabic->id,
                'classroom_id' => $class->id,
                'academic_year_id' => $year->id,
            ],
            [
                'academic_year' => $year->name,
                'term_id' => $term->id,
                'title' => 'Grade 5 Arabic Term 1',
                'status' => 'active',
            ],
        );
        PlanTopic::query()->firstOrCreate(
            ['course_plan_id' => $plan->id, 'order' => 1],
            [
                'title' => 'Sun and moon letters',
                'objective' => 'Read al- with sun and moon letters',
                'estimated_minutes' => 45,
                'is_completed' => false,
            ],
        );

        $students = $this->students($school->id);
        $assign = app(AssignStudentToClassAction::class);
        foreach ($students as $student) {
            $assign->execute($class, $student->id, '2026-01-05');
        }

        $this->guardians($students);

        $tuition = FeeItem::query()->where('name', 'Pilot tuition')->first()
            ?? app(SaveFeeItemAction::class)->execute([
                'name' => 'Pilot tuition',
                'default_amount' => 1500,
                'type' => FeeItemType::Tuition->value,
                'frequency' => FeeFrequency::Monthly->value,
                'is_mandatory' => true,
            ]);

        if (! FeeStructure::query()->where('name', 'Pilot Grade 5 fees')->exists()) {
            app(SaveFeeStructureAction::class)->execute([
                'academic_year_id' => $year->id,
                'name' => 'Pilot Grade 5 fees',
                'applies_to' => FeeStructureAppliesTo::SelectedClasses->value,
                'class_ids' => [$class->id],
                'status' => FeeStructureStatus::Active->value,
                'items' => [[
                    'fee_item_id' => $tuition->id,
                    'amount' => 1500,
                    'frequency' => FeeFrequency::Monthly->value,
                    'due_day' => 5,
                    'is_mandatory' => true,
                ]],
            ]);
        }

        $this->command?->info('Pilot rehearsal: '.self::YEAR_NAME.' / '.self::CLASS_NAME.' '.self::CLASS_SECTION.' / '.count($students).' students.');
        $this->command?->info('Logins (password): admin@akuru.edu.mv, teacher@akuru.edu.mv, parent@akuru.edu.mv');
        $this->command?->info('Mark Fatima Yoosuf (PIL-01) absent to exercise SMS + absence note.');
    }

    /**
     * @return list<Student>
     */
    private function students(int $schoolId): array
    {
        $rows = [
            ['PIL-01', 'Fatima', 'Yoosuf', '2010-03-12', 'female', 'A999001'],
            ['PIL-02', 'Hussain', 'Shareef', '2008-07-22', 'male', 'A999001'],
            ['PIL-03', 'Aisha', 'Mohamed', '2013-02-02', 'female', null],
            ['PIL-04', 'Ibrahim', 'Rasheed', '2014-05-05', 'male', 'N/A'],
            ['PIL-05', 'Hawwa', 'Zahir', '2015-08-08', 'female', '0'],
            ['PIL-06', 'Yoosuf', 'Manik', '2016-09-09', 'male', '-'],
            ['PIL-07', 'Mariyam', 'Ali', '2011-01-01', 'female', 'A700001'],
            ['PIL-08', 'Mariyam', 'Ali', '2012-06-15', 'female', 'A700002'],
            ['PIL-09', 'Ahmed', 'Naseem', '2009-04-04', 'male', 'A800001'],
            ['PIL-10', 'Ahmed', 'Naseem', '2009-04-04', 'male', 'A800002'],
            ['PIL-11', 'Aminath', 'Rishfa', '2011-11-11', 'female', 'A155111'],
            ['PIL-12', 'Ismail', 'Nizar', '2012-04-18', 'male', 'A155112'],
            ['PIL-13', 'Khadheeja', 'Didi', '2010-09-21', 'female', 'A155113'],
            ['PIL-14', 'Mohamed', 'Faisal', '2013-07-07', 'male', 'A155114'],
            ['PIL-15', 'Fathimath', 'Sama', '2014-12-01', 'female', 'A155115'],
        ];

        $students = [];
        foreach ($rows as [$number, $first, $last, $dob, $gender, $nid]) {
            $students[] = Student::query()->firstOrCreate(
                ['student_id' => $number],
                [
                    'user_id' => null,
                    'school_id' => $schoolId,
                    'first_name' => $first,
                    'last_name' => $last,
                    'date_of_birth' => $dob,
                    'gender' => $gender,
                    'national_id' => $nid,
                    'admission_date' => '2026-01-05',
                    'status' => 'active',
                    'nationality' => 'MV',
                    'address' => 'Malé',
                ],
            );
        }

        return $students;
    }

    /**
     * @param  list<Student>  $students
     */
    private function guardians(array $students): void
    {
        $byNumber = collect($students)->keyBy('student_id');
        $attach = app(AttachGuardianAction::class);

        $parentUser = User::query()->where('email', self::PARENT_EMAIL)->first();
        if ($parentUser !== null) {
            $fatimaGuardian = ParentGuardian::query()->firstOrCreate(
                ['user_id' => $parentUser->id],
                [
                    'first_name' => 'Hassan',
                    'last_name' => 'Ahmed',
                    'phone' => '7820288',
                    'email' => $parentUser->email,
                    'address' => 'Malé',
                    'relationship' => 'father',
                ],
            );
            $fatima = $byNumber->get('PIL-01');
            if ($fatima && ! $fatima->guardians()->where('parent_guardians.id', $fatimaGuardian->id)->exists()) {
                $attach->execute($fatima, $fatimaGuardian, 'father', true, true, true);
            }
        }

        $shared = $this->guardianUser('pilot.guardian@akuru.edu.mv', 'Shared Guardian', '7972434');
        foreach (['PIL-03', 'PIL-04', 'PIL-05', 'PIL-06', 'PIL-07', 'PIL-08', 'PIL-11', 'PIL-12', 'PIL-13', 'PIL-14', 'PIL-15'] as $number) {
            $student = $byNumber->get($number);
            if ($student === null || $student->guardians()->where('parent_guardians.id', $shared->id)->exists()) {
                continue;
            }
            $attach->execute($student, $shared, 'mother', true, true, true);
        }

        $naseemDad = $this->guardianUser('pilot.naseem@akuru.edu.mv', 'Naseem Father', '7820288');
        foreach (['PIL-09', 'PIL-10'] as $number) {
            $student = $byNumber->get($number);
            if ($student === null || $student->guardians()->where('parent_guardians.id', $naseemDad->id)->exists()) {
                continue;
            }
            $attach->execute($student, $naseemDad, 'father', true, true, true);
        }
    }

    private function guardianUser(string $email, string $name, string $phone): ParentGuardian
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'phone' => $phone,
                'address' => 'Malé',
                'is_active' => true,
            ],
        );
        if (! $user->hasRole('parent')) {
            $user->assignRole('parent');
        }

        return ParentGuardian::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => explode(' ', $name)[0],
                'last_name' => explode(' ', $name)[1] ?? 'Guardian',
                'phone' => $phone,
                'email' => $email,
                'address' => 'Malé',
                'relationship' => 'guardian',
            ],
        );
    }

    private function teacher(
        int $schoolId,
        string $email,
        string $first,
        string $last,
        string $teacherId,
        string $specialization,
    ): Teacher {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => trim($first.' '.$last),
                'password' => Hash::make('password'),
                'phone' => '7820288',
                'address' => 'Malé',
                'is_active' => true,
            ],
        );
        if (! $user->hasRole('teacher')) {
            $user->assignRole('teacher');
        }

        return Teacher::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'school_id' => $schoolId,
                'teacher_id' => $teacherId,
                'first_name' => $first,
                'last_name' => $last,
                'date_of_birth' => '1985-03-20',
                'gender' => 'male',
                'phone' => '7820288',
                'address' => 'Malé',
                'email' => $email,
                'qualification' => 'BA',
                'specialization' => $specialization,
                'joining_date' => '2020-01-01',
                'status' => 'active',
            ],
        );
    }
}
