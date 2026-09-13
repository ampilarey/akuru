<?php

namespace App\Domains\People\Enums;

/**
 * SPEC §9's "Verification status", with `verified_at` beside it.
 *
 * **This does not gate the parent portal, and must not.** The temptation is
 * obvious — the column says `unverified`, so filter on it — but every link in
 * the database has said `unverified` since the table shipped, because nothing
 * could ever set it. Enforcing it as an access rule would hide **every** child
 * from **every** parent overnight. That is a regression wearing the costume of
 * a security fix.
 *
 * What it is instead: a record of whether a member of staff has checked that
 * this adult really is this child's guardian. `/portal/children` stays scoped
 * the way it always was — to the signed-in guardian's own links — and this
 * column tells staff which of those links anyone has actually looked at.
 *
 * Turning it into an access gate is a separate decision with its own migration
 * of existing links, and it belongs to the owner, not to this slice.
 */
enum GuardianVerificationStatus: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Not checked',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * §9 pairs the status with `verified_at`, so the two move together: a
     * timestamp is stamped when someone verifies and cleared when the link goes
     * back to unchecked, rather than lingering to say a link was verified at a
     * moment when it now is not.
     */
    public function stampsVerifiedAt(): bool
    {
        return $this === self::Verified;
    }
}
