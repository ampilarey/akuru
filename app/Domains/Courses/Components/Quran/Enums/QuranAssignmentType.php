<?php

namespace App\Domains\Courses\Components\Quran\Enums;

/**
 * SPEC §52.18 assignment types.
 */
enum QuranAssignmentType: string
{
    case LetterHarakaPractice = 'letter_haraka_practice';
    case NewMemorization = 'new_memorization';
    case Revision = 'revision';
    case CorrectionRepeat = 'correction_repeat';
    case Assessment = 'assessment';
}
