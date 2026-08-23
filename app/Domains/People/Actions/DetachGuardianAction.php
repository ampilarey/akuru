<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\Student;

class DetachGuardianAction
{
    public function execute(Student $student, ParentGuardian $guardian): void
    {
        $student->guardians()->detach($guardian->id);
    }
}
