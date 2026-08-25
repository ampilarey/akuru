<?php

namespace App\Domains\HR\Enums;

enum JobApplicationStatus: string
{
    case Received = 'received';
    case Shortlisted = 'shortlisted';
    case Interview = 'interview';
    case Offer = 'offer';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
}
