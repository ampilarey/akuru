<?php

namespace App\Domains\ExamsGrades\Enums;

enum ReportCardStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Published = 'published';
}
