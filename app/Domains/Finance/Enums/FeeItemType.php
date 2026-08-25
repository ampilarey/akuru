<?php

namespace App\Domains\Finance\Enums;

enum FeeItemType: string
{
    case Tuition = 'tuition';
    case Registration = 'registration';
    case Examination = 'examination';
    case Activity = 'activity';
    case Transport = 'transport';
    case Books = 'books';
    case Uniform = 'uniform';
    case Other = 'other';
}
