<?php

namespace App\Domains\Finance\Enums;

enum InvoiceMonthlyMode: string
{
    case PerMonth = 'per_month';
    case Consolidated = 'consolidated';
}
