<?php

namespace App\Domains\ExamsGrades\Enums;

enum AwardLevel: string
{
    case Classroom = 'class';
    case School = 'school';
}
