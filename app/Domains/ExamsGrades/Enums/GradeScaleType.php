<?php

namespace App\Domains\ExamsGrades\Enums;

enum GradeScaleType: string
{
    case PercentageBands = 'percentage_bands';
    case Letter = 'letter';
    case CompetencyLevels = 'competency_levels';
}
