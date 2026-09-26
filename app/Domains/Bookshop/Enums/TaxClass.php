<?php

namespace App\Domains\Bookshop\Enums;

/**
 * BOOKSHOP_PLAN §8 (audit): books are commonly zero-rated or exempt while
 * stationery is standard, so the class is per product. Rates live in the
 * office settings (decision 4), never here.
 */
enum TaxClass: string
{
    case Standard = 'standard';
    case ZeroRated = 'zero_rated';
    case Exempt = 'exempt';
}
