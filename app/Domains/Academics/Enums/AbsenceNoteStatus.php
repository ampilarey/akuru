<?php

namespace App\Domains\Academics\Enums;

enum AbsenceNoteStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
