<?php

namespace App\Policies;

use App\Models\RegistrationStudent;
use App\Models\User;

class RegistrationStudentPolicy
{
    public function manage(User $user, RegistrationStudent $student): bool
    {
        return $user->guardianStudents()->where('registration_students.id', $student->id)->exists()
            || $student->user_id === $user->id;
    }
}
