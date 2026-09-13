<?php

namespace App\Domains\People\Enums;

/**
 * SPEC §9 "Parent-Child Relationship" lists nine things the `guardian_student`
 * pivot must support. Four of them were written from the day the table shipped:
 * relationship type, and the primary/pickup/financial flags.
 *
 * The other five — **consent status**, **verification status**, `verified_at`,
 * `created_by` and notes — were added by migration `1A.7` and then written by
 * nobody. `AttachGuardianAction` sets four columns and leaves these at their
 * defaults, so every link in the database has read `unknown` / `unverified`
 * since the table was created, and no code path could ever change that.
 *
 * A field that can only ever hold its default is supported in name only.
 *
 * `unknown` is deliberately the default rather than `refused`: not having asked
 * is a different fact from having been told no, and a school that cannot tell
 * the two apart will either spam a family or go silent on one.
 */
enum GuardianConsentStatus: string
{
    case Unknown = 'unknown';
    case Granted = 'granted';
    case Refused = 'refused';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Not asked',
            self::Granted => 'Granted',
            self::Refused => 'Refused',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * Withdrawn is not the same as refused — consent was given and then taken
     * back — but both mean the same thing today: do not act on it.
     */
    public function allowsContact(): bool
    {
        return $this === self::Granted;
    }
}
