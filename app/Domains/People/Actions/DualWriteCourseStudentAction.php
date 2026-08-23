<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\StudentStatus;
use App\Domains\People\Models\RegistrationStudent;
use App\Domains\People\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Deploy 2 dual-write: keep registration_students as the write-side legacy row
 * and mirror it onto students (legacy_registration_student_id).
 */
class DualWriteCourseStudentAction
{
    public function sync(RegistrationStudent $rs): Student
    {
        $rs->refresh();

        $student = Student::query()->where('legacy_registration_student_id', $rs->id)->first();

        if ($student === null && $rs->user_id !== null) {
            $student = Student::query()->where('user_id', $rs->user_id)->first();
        }

        $nationalId = $this->plain($rs->national_id);
        $passport = $this->plain($rs->passport);

        if ($student === null) {
            $hasActiveEnrollment = DB::table('course_enrollments')
                ->where('student_id', $rs->id)
                ->where('status', 'active')
                ->exists();

            $student = new Student;
            $student->forceFill([
                'user_id' => $rs->user_id,
                'school_id' => null,
                'class_id' => null,
                'student_id' => null,
                'admission_date' => null,
                'first_name' => $rs->first_name,
                'last_name' => $rs->last_name,
                'date_of_birth' => $rs->dob,
                'gender' => $rs->gender ?? 'male',
                'national_id' => $nationalId,
                'passport' => $passport,
                'legacy_registration_student_id' => $rs->id,
                'status' => $hasActiveEnrollment ? StudentStatus::Active : StudentStatus::Prospective,
            ]);
            $student->save();

            return $student;
        }

        $student->first_name = $rs->first_name;
        $student->last_name = $rs->last_name;
        $student->date_of_birth = $rs->dob;
        if ($rs->gender !== null) {
            $student->gender = $rs->gender;
        }
        if ($rs->user_id !== null) {
            $student->user_id = $rs->user_id;
        }
        if ($this->plain($student->national_id) === null && $nationalId !== null) {
            $student->national_id = $nationalId;
        }
        if ($this->plain($student->passport) === null && $passport !== null) {
            $student->passport = $passport;
        }
        $student->legacy_registration_student_id = $rs->id;
        $student->save();

        return $student;
    }

    private function plain(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
