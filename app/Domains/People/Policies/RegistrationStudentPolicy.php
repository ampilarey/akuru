<?php

namespace App\Domains\People\Policies;

use App\Domains\People\Models\RegistrationStudent;
use App\Domains\Identity\Models\User;

class RegistrationStudentPolicy
{
    public function manage(User $user, RegistrationStudent $student): bool
    {
        return $user->guardianStudents()->where('registration_students.id', $student->id)->exists()
            || $student->user_id === $user->id;
    }
}
