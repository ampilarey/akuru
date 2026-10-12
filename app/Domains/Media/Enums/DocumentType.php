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
    case CourseCertificate = 'course_certificate';
    case IdCard = 'id_card';
    case TransferCertificate = 'transfer_certificate';
    case Receipt = 'receipt';
    case Payslip = 'payslip';
    // NAME_TAGS_PLAN §5.2 (NT3): a print job's watermarked proof sheet, and its print-ready PDF.
    case PrintProof = 'print_proof';
    case PrintFile = 'print_file';
    // NT5: the customer's own artwork for a print job — private, read only to proof and print it.
    case PrintUpload = 'print_upload';
    case Other = 'other';
}
