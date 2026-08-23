<?php

namespace App\Domains\People\Enums;

enum StudentStatus: string
{
    case Prospective = 'prospective';
    case Active = 'active';
    case Inactive = 'inactive';
    case Graduated = 'graduated';
    case Transferred = 'transferred';
    case Withdrawn = 'withdrawn';
}
