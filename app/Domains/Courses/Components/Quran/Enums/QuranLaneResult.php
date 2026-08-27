<?php

namespace App\Domains\Courses\Components\Quran\Enums;

/**
 * The "new memorization" lane's outcomes — value-identical to the legacy
 * Hifz lane so F5's data migration is a straight copy.
 */
enum QuranLaneResult: string
{
    case Pass = 'pass';
    case PassWithNotes = 'pass_with_notes';
    case Repeat = 'repeat';
    case NotPrepared = 'not_prepared';
    case NotDone = 'not_done';
}
