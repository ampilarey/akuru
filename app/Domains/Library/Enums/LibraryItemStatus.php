<?php

namespace App\Domains\Library\Enums;

enum LibraryItemStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ChangesRequested = 'changes_requested';
    case Rejected = 'rejected';
    case Published = 'published';
    case Archived = 'archived';
}
