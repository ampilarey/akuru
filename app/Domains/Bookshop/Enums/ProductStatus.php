<?php

namespace App\Domains\Bookshop\Enums;

/** Only `active` products are for sale (B1b shows them; B2 sells them). */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
