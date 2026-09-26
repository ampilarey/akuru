<?php

namespace App\Domains\Bookshop\DTOs;

use App\Domains\Bookshop\Enums\VendorMemberRole;

/**
 * Who is acting, for which vendor, as what (BOOKSHOP_PLAN §10 "Security").
 *
 * Every vendor-portal Action takes one of these as its first argument and
 * reads and writes only rows whose `vendor_id` is `$vendorId`. The only way
 * to get one is `ResolveVendorScopeAction`, which reads the membership row —
 * so "may this person touch this vendor's products" has one answer, the way
 * `VerifiedGuardianLink` is the one answer for families.
 * `VendorScopeIsTheOnlyDoorTest` holds the portal to it.
 */
final readonly class VendorScope
{
    public function __construct(
        public int $vendorId,
        public int $userId,
        public VendorMemberRole $role,
        public string $vendorName,
        public string $vendorSlug,
        public bool $agreementAccepted,
    ) {}

    public function isOwner(): bool
    {
        return $this->role === VendorMemberRole::Owner;
    }
}
