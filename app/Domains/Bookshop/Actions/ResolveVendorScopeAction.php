<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\VendorMember;

/**
 * The one door into the vendor portal: a person's membership of an active
 * vendor. A person in several vendors picks one (the portal's switcher
 * keeps it in the session); without a pick, the oldest membership wins.
 * Returns null for anyone who is not a member of an active vendor — the
 * caller turns that into a 403.
 */
class ResolveVendorScopeAction
{
    public function execute(int $userId, ?int $preferredVendorId = null): ?VendorScope
    {
        $memberships = VendorMember::query()
            ->with('vendor')
            ->where('user_id', $userId)
            ->whereHas('vendor', fn ($q) => $q->where('status', VendorStatus::Active->value))
            ->orderBy('id')
            ->get();

        $member = $memberships->firstWhere('vendor_id', $preferredVendorId) ?? $memberships->first();
        if ($member === null) {
            return null;
        }

        return new VendorScope(
            vendorId: (int) $member->vendor_id,
            userId: $userId,
            role: $member->role,
            vendorName: (string) $member->vendor->name,
            vendorSlug: (string) $member->vendor->slug,
            agreementAccepted: $member->agreement_accepted_at !== null,
        );
    }

    /**
     * Every active vendor this person belongs to, for the switcher.
     *
     * @return list<array{id: int, name: string, role: string}>
     */
    public function memberships(int $userId): array
    {
        return VendorMember::query()
            ->with('vendor')
            ->where('user_id', $userId)
            ->whereHas('vendor', fn ($q) => $q->where('status', VendorStatus::Active->value))
            ->orderBy('id')
            ->get()
            ->map(fn (VendorMember $m) => [
                'id' => (int) $m->vendor_id,
                'name' => (string) $m->vendor->name,
                'role' => $m->role->value,
            ])->values()->all();
    }
}
