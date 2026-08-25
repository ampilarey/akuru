<?php

namespace App\Domains\Finance\Enums;

enum InvoiceType: string
{
    case SchoolFees = 'school_fees';
    case CourseFee = 'course_fee';
    case Other = 'other';
}
