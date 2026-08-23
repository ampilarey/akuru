<?php

namespace App\Domains\Media\Enums;

enum DocumentType: string
{
    case BirthCertificate = 'birth_certificate';
    case NationalId = 'national_id';
    case Passport = 'passport';
    case Photo = 'photo';
    case Other = 'other';
}
