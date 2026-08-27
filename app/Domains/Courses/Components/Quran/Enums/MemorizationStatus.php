<?php

namespace App\Domains\Courses\Components\Quran\Enums;

enum MemorizationStatus: string
{
    case NotStarted = 'not_started';
    case Learning = 'learning';
    case Submitted = 'submitted';
    case Passed = 'passed';
    case NeedsRevision = 'needs_revision';
    case Weak = 'weak';
    case Strong = 'strong';
}
