<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\RegistrationStudent;
use App\Domains\People\Models\Student;

class EnsureLegacyStudentForUnifiedAction
{
    public function execute(int $unifiedStudentId): int
    {
        $student = Student::query()->findOrFail($unifiedStudentId);
        if ($student->legacy_registration_student_id) {
            return (int) $student->legacy_registration_student_id;
        }

        if ($student->user_id) {
            $existing = RegistrationStudent::query()->where('user_id', $student->user_id)->first();
            if ($existing !== null) {
                $student->legacy_registration_student_id = $existing->id;
                $student->save();

                return (int) $existing->id;
            }
        }

        $legacy = RegistrationStudent::query()->create([
            'user_id' => $student->user_id,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'dob' => $student->date_of_birth,
            'gender' => $student->gender instanceof \BackedEnum
                ? $student->gender->value
                : (string) ($student->gender ?: 'male'),
        ]);

        $student->legacy_registration_student_id = $legacy->id;
        $student->save();

        return $legacy->id;
    }
}
