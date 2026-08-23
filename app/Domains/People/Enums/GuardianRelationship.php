<?php

namespace App\Domains\People\Enums;

enum GuardianRelationship: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';
    case Grandfather = 'grandfather';
    case Grandmother = 'grandmother';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    case Other = 'other';
}
