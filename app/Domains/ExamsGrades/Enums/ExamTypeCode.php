<?php

namespace App\Domains\ExamsGrades\Enums;

enum ExamTypeCode: string
{
    case Midterm = 'midterm';
    case Final = 'final';
    case Quiz = 'quiz';
    case Assignment = 'assignment';
    case Practical = 'practical';
    case Oral = 'oral';
}
