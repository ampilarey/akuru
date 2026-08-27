<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Models\Student;

class CountStudentsAction
{
    public function execute(): int
    {
        return Student::query()->count();
    }
}
