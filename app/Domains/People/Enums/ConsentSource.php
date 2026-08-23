<?php

namespace App\Domains\People\Enums;

enum ConsentSource: string
{
    case AdmissionForm = 'admission_form';
    case Portal = 'portal';
    case Admin = 'admin';
}
