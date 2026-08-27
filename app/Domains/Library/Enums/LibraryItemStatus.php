<?php

namespace App\Domains\Library\Enums;

enum LibraryItemStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
