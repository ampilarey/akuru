<?php

namespace App\Domains\People\Support;

use App\Domains\People\Enums\GuardianVerificationStatus;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * The one definition of "a link the family may act through" (rule 11).
 *
 * OWNER_ACTIONS item 13 (2026-09-25): a parent reaches a child, and a
 * child's news reaches a parent, only over a link the office has
 * **verified**. Every reader of `guardian_student` that answers a
 * family-facing question goes through here — the portal's children, the
 * collectable children, the message and notification fan-out — so the rule
 * lives once, and an unverified link created by a stranger on the public
 * registration form is invisible to them until the office confirms it.
 *
 * What does NOT go through here, on purpose: the office's own reads. The
 * student profile lists every link, verified or not, because that is where
 * the office decides.
 */
final class VerifiedGuardianLink
{
    public static function scopeQuery(QueryBuilder $query, string $table = 'guardian_student'): QueryBuilder
    {
        return $query->where($table.'.verification_status', GuardianVerificationStatus::Verified->value);
    }

    public static function scopeRelation(BelongsToMany $relation): BelongsToMany
    {
        return $relation->wherePivot('verification_status', GuardianVerificationStatus::Verified->value);
    }
}
