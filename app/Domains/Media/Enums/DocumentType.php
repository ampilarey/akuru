<?php

namespace App\Domains\Media\Enums;

enum DocumentType: string
{
    case BirthCertificate = 'birth_certificate';
    case NationalId = 'national_id';
    case Passport = 'passport';
    case Photo = 'photo';
    case ReportCard = 'report_card';
    case Transcript = 'transcript';
    case Other = 'other';
}
