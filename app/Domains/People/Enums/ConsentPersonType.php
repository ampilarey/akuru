<?php

namespace App\Domains\People\Enums;

enum ConsentPersonType: string
{
    case Student = 'student';
    case Guardian = 'guardian';
}
