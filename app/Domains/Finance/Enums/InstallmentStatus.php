<?php

namespace App\Domains\Finance\Enums;

enum InstallmentStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
}
