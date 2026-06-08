<?php

namespace App\Enums\Hifz;

enum HifzProgramStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Completed = 'completed';
}
