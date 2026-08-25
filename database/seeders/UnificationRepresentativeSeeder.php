<?php

namespace Database\Seeders;

use App\Domains\Courses\Models\Course;
use App\Domains\Finance\Models\Payment;
use App\Domains\Identity\Models\User;
use App\Domains\People\Models\RegistrationStudent;
use App\Domains\People\Models\Student;
use App\Domains\People\Support\RepresentativeUnificationGate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ADR-021 representative unification dataset. Not added to DatabaseSeeder.
 * Invoked by `students:verify-unification --representative` and Pest.
 */
class UnificationRepresentativeSeeder extends Seeder
{
    /**
     * @var array<string, mixed>
     */
    public array $manifest = [
        'resolvable_registration_student_ids' => [],
        'expected_unresolved_registration_student_ids' => [],
        'expected_unmapped_guardian_pivot_ids' => [],
        'expected_missing_enrollment_ids' => [],
        'scenarios' => [],
    ];

    public function run(): void
    {
        $course = Course::factory()->create([
            'title' => 'Unification representative course',
        ]);

        $payer = User::factory()->create(['name' => 'Representative Payer']);

        $this->duplicateNationalIds($course, $payer);
        $this->blankAndPlaceholders($course, $payer);
        $this->sameNameDifferentDob($course, $payer);
        $this->contradictingNationalId($course, $payer);
        $this->childWithoutUserWithGuardian($course, $payer);
        $this->genuineNameDobDuplicate($course, $payer);

        $this->writeManifest();
    }

    private function duplicateNationalIds(Course $course, User $payer): void
    {
        $shared = 'A999001';

        $fatimaStudent = $this->student([
            'first_name' => 'Fatima',
            'last_name' => 'Yoosuf',
            'date_of_birth' => '2010-03-12',
            'gender' => 'female',
            'national_id' => $shared,
            'user_id' => null,
        ]);
        $hussainStudent = $this->student([
            'first_name' => 'Hussain',
            'last_name' => 'Shareef',
            'date_of_birth' => '2008-07-22',
            'gender' => 'male',
            'national_id' => $shared,
            'user_id' => null,
        ]);

        $fatimaRs = $this->registration([
            'first_name' => 'Fatima',
            'last_name' => 'Yoosuf',
            'dob' => '2010-03-12',
            'gender' => 'female',
            'national_id' => $shared,
            'user_id' => null,
        ]);
        $hussainRs = $this->registration([
            'first_name' => 'Hussain',
            'last_name' => 'Shareef',
            'dob' => '2008-07-22',
            'gender' => 'male',
            'national_id' => $shared,
            'user_id' => null,
        ]);

        $guardian = User::factory()->create(['name' => 'Alive Guardian']);
        $this->legacyGuardian($fatimaRs->id, $guardian->id, 'mother');

        $gone = User::factory()->create(['name' => 'Gone Guardian']);
        $orphanPivot = $this->legacyGuardian($hussainRs->id, $gone->id, 'father');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('users')->where('id', $gone->id)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->enrollAndPay($fatimaRs->id, $course, $payer, 'FATIMA');
        $this->enrollAndPay($hussainRs->id, $course, $payer, 'HUSSAIN');

        $this->resolvable($fatimaRs->id, 'duplicate_nid_fatima', $fatimaStudent->id);
        $this->resolvable($hussainRs->id, 'duplicate_nid_hussain', $hussainStudent->id);
        $this->manifest['expected_unmapped_guardian_pivot_ids'][] = $orphanPivot;
        $this->manifest['scenarios']['orphan_guardian_pivot_id'] = $orphanPivot;
    }

    private function blankAndPlaceholders(Course $course, User $payer): void
    {
        $cases = [
            ['Aisha', 'Mohamed', '2013-02-02', 'female', null, 'blank_nid'],
            ['Ibrahim', 'Rasheed', '2014-05-05', 'male', 'N/A', 'placeholder_na'],
            ['Hawwa', 'Zahir', '2015-08-08', 'female', '0', 'placeholder_zero'],
            ['Yoosuf', 'Manik', '2016-09-09', 'male', '-', 'placeholder_dash'],
        ];

        foreach ($cases as [$first, $last, $dob, $gender, $nid, $key]) {
            $student = $this->student([
                'first_name' => $first,
                'last_name' => $last,
                'date_of_birth' => $dob,
                'gender' => $gender,
                'national_id' => $nid,
                'user_id' => null,
            ]);
            $rs = $this->registration([
                'first_name' => $first,
                'last_name' => $last,
                'dob' => $dob,
                'gender' => $gender,
                'national_id' => $nid,
                'user_id' => null,
            ]);
            $this->enrollAndPay($rs->id, $course, $payer, strtoupper($key));
            $this->resolvable($rs->id, $key, $student->id);
        }
    }

    private function sameNameDifferentDob(Course $course, User $payer): void
    {
        $older = $this->student([
            'first_name' => 'Mariyam',
            'last_name' => 'Ali',
            'date_of_birth' => '2011-01-01',
            'gender' => 'female',
            'national_id' => 'A700001',
            'user_id' => null,
        ]);
        $younger = $this->student([
            'first_name' => 'Mariyam',
            'last_name' => 'Ali',
            'date_of_birth' => '2012-06-15',
            'gender' => 'female',
            'national_id' => 'A700002',
            'user_id' => null,
        ]);

        $olderRs = $this->registration([
            'first_name' => 'Mariyam',
            'last_name' => 'Ali',
            'dob' => '2011-01-01',
            'gender' => 'female',
            'national_id' => 'A700001',
            'user_id' => null,
        ]);
        $youngerRs = $this->registration([
            'first_name' => 'Mariyam',
            'last_name' => 'Ali',
            'dob' => '2012-06-15',
            'gender' => 'female',
            'national_id' => 'A700002',
            'user_id' => null,
        ]);

        $this->enrollAndPay($olderRs->id, $course, $payer, 'MARIYAM-OLDER');
        $this->enrollAndPay($youngerRs->id, $course, $payer, 'MARIYAM-YOUNGER');

        $this->resolvable($olderRs->id, 'same_name_older', $older->id);
        $this->resolvable($youngerRs->id, 'same_name_younger', $younger->id);
    }

    private function contradictingNationalId(Course $course, User $payer): void
    {
        $wrong = $this->student([
            'first_name' => 'Wrong',
            'last_name' => 'Hit',
            'date_of_birth' => '2000-01-01',
            'gender' => 'male',
            'national_id' => 'A888888',
            'user_id' => null,
        ]);
        $right = $this->student([
            'first_name' => 'Right',
            'last_name' => 'Kid',
            'date_of_birth' => '2011-02-02',
            'gender' => 'female',
            'national_id' => null,
            'user_id' => null,
        ]);

        $rs = $this->registration([
            'first_name' => 'Right',
            'last_name' => 'Kid',
            'dob' => '2011-02-02',
            'gender' => 'female',
            'national_id' => 'A888888',
            'user_id' => null,
        ]);

        $this->enrollAndPay($rs->id, $course, $payer, 'CONTRADICTION');
        $this->resolvable($rs->id, 'nid_contradiction', $right->id);
        $this->manifest['scenarios']['nid_contradiction_wrong_student_id'] = $wrong->id;
    }

    private function childWithoutUserWithGuardian(Course $course, User $payer): void
    {
        $student = $this->student([
            'first_name' => 'Small',
            'last_name' => 'Child',
            'date_of_birth' => '2018-11-11',
            'gender' => 'female',
            'national_id' => 'A600001',
            'user_id' => null,
        ]);
        $rs = $this->registration([
            'first_name' => 'Small',
            'last_name' => 'Child',
            'dob' => '2018-11-11',
            'gender' => 'female',
            'national_id' => 'A600001',
            'user_id' => null,
        ]);
        $parent = User::factory()->create(['name' => 'Parent Login']);
        $this->legacyGuardian($rs->id, $parent->id, 'guardian');
        $this->enrollAndPay($rs->id, $course, $payer, 'SMALL-CHILD');
        $this->resolvable($rs->id, 'child_without_user', $student->id);
    }

    private function genuineNameDobDuplicate(Course $course, User $payer): void
    {
        $this->student([
            'first_name' => 'Ahmed',
            'last_name' => 'Naseem',
            'date_of_birth' => '2009-04-04',
            'gender' => 'male',
            'national_id' => null,
            'user_id' => null,
        ]);
        $this->student([
            'first_name' => 'Ahmed',
            'last_name' => 'Naseem',
            'date_of_birth' => '2009-04-04',
            'gender' => 'male',
            'national_id' => null,
            'user_id' => null,
        ]);

        $first = $this->registration([
            'first_name' => 'Ahmed',
            'last_name' => 'Naseem',
            'dob' => '2009-04-04',
            'gender' => 'male',
            'national_id' => null,
            'user_id' => null,
        ]);
        $second = $this->registration([
            'first_name' => 'Ahmed',
            'last_name' => 'Naseem',
            'dob' => '2009-04-04',
            'gender' => 'male',
            'national_id' => null,
            'user_id' => null,
        ]);

        $enrollFirst = $this->enrollAndPay($first->id, $course, $payer, 'AHMED-1');
        $enrollSecond = $this->enrollAndPay($second->id, $course, $payer, 'AHMED-2');

        $this->manifest['expected_unresolved_registration_student_ids'][] = $first->id;
        $this->manifest['expected_unresolved_registration_student_ids'][] = $second->id;
        $this->manifest['expected_missing_enrollment_ids'][] = $enrollFirst;
        $this->manifest['expected_missing_enrollment_ids'][] = $enrollSecond;
        $this->manifest['scenarios']['genuine_duplicate_rs_ids'] = [$first->id, $second->id];
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function student(array $attrs): Student
    {
        return Student::query()->create(array_merge([
            'status' => 'active',
            'gender' => 'female',
        ], $attrs));
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function registration(array $attrs): RegistrationStudent
    {
        return RegistrationStudent::query()->create($attrs);
    }

    private function legacyGuardian(int $rsId, int $userId, string $relationship): int
    {
        return (int) DB::table('student_guardians')->insertGetId([
            'student_id' => $rsId,
            'guardian_user_id' => $userId,
            'relationship' => $relationship,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function enrollAndPay(int $rsId, Course $course, User $payer, string $tag): int
    {
        $enrollmentId = (int) DB::table('course_enrollments')->insertGetId([
            'student_id' => $rsId,
            'course_id' => $course->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Payment::query()->create([
            'user_id' => $payer->id,
            'student_id' => $rsId,
            'course_id' => $course->id,
            'amount' => 150,
            'currency' => 'MVR',
            'status' => 'confirmed',
            'provider' => 'bml',
            'merchant_reference' => 'UNIF-'.$tag.'-'.Str::uuid(),
        ]);

        return $enrollmentId;
    }

    private function resolvable(int $rsId, string $scenario, int $studentId): void
    {
        $this->manifest['resolvable_registration_student_ids'][] = $rsId;
        $this->manifest['scenarios'][$scenario] = [
            'registration_student_id' => $rsId,
            'student_id' => $studentId,
        ];
    }

    private function writeManifest(): void
    {
        $path = RepresentativeUnificationGate::manifestPath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n"
        );
    }
}
