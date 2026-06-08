<?php

namespace App\Enums\Hifz;

enum HifzLaneResult: string
{
    case Pass = 'pass';
    case PassWithNotes = 'pass_with_notes';
    case Repeat = 'repeat';
    case NotPrepared = 'not_prepared';
    case NotDone = 'not_done';
}
