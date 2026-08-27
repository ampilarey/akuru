<?php

namespace App\Domains\Courses\Components\Quran\Enums;

enum QuranMistakeSeverity: string
{
    case Minor = 'minor';
    case Medium = 'medium';
    case Major = 'major';
}
