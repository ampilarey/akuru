<?php

namespace App\Domains\Academics\Enums;

enum ClassStudentStatus: string
{
    case Active = 'active';
    case Promoted = 'promoted';
    case Left = 'left';
}
