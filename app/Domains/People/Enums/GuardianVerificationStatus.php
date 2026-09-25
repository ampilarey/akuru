<?php

namespace App\Domains\People\Enums;

/**
 * SPEC §9's "Verification status", with `verified_at` beside it.
 *
 * **A gate since 2026-09-25** (OWNER_ACTIONS item 13). A parent reaches a
 * child, and a child's news reaches a parent, only over a `verified` link —
 * see `VerifiedGuardianLink`, the one place the rule is written.
 *
 * It was a record only until then, and deliberately: every link in the
 * database had said `unverified` since the table shipped because nothing
 * could set it, so gating without a backfill would have hidden every child
 * from every parent overnight. The backfill migration of the same day marked
 * every then-existing link verified (all made by the office or a seeder), the
 * office's own attach verifies as it goes, and only a link a parent creates
 * on the public registration form starts `unverified` — shown to them as
 * *awaiting the office* until a member of staff checks it.
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
