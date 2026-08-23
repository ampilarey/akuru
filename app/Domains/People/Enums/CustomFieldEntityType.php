<?php

namespace App\Domains\People\Enums;

enum CustomFieldEntityType: string
{
    case Students = 'students';
    case Staff = 'staff';
    case AdmissionApplications = 'admission_applications';
}
