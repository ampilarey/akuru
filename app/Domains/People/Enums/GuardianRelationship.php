<?php

namespace App\Domains\People\Enums;

enum GuardianRelationship: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';
    case Grandfather = 'grandfather';
    case Grandmother = 'grandmother';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    // SPEC §9 names five example relationship types — "Father · Mother ·
    // Guardian · **Sponsor** · Other" — and this enum had every one but
    // sponsor. It is not a synonym for `other`: a sponsor pays for a child's
    // schooling without standing in a parent's place, which is exactly the
    // distinction `financial_responsible` is for, and folding it into `other`
    // loses the one fact anybody would look it up for.
    case Sponsor = 'sponsor';
    case Other = 'other';
}
