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
    case AwardCertificate = 'award_certificate';
    case IdCard = 'id_card';
    case TransferCertificate = 'transfer_certificate';
    case Receipt = 'receipt';
    case Other = 'other';
}
