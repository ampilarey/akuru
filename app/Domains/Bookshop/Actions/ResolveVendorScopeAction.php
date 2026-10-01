<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Enums\VendorMemberRole;
use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;

/**
 * The one door into the vendor portal: a person's membership of an active
 * vendor, or a paused one (STATUS §5lo: it finishes its orders). A person in several vendors picks one (the portal's switcher
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
            ->whereHas('vendor', fn ($q) => $q->whereIn('status', VendorStatus::portalOpen()))
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
            paused: $member->vendor->status === VendorStatus::Paused,
        );
    }

    /**
     * COMMERCE_PARITY_PLAN P6a: the office acting for a shop on the orders
     * Akuru packs — the caller has checked `bookshop.manage`. Never a member's
     * scope: `office` is set, and the fulfilment action accepts it only for
     * an order Akuru packs.
     */
    public function forOffice(int $vendorId, int $officeUserId): VendorScope
    {
        $vendor = Vendor::query()->findOrFail($vendorId);

        return new VendorScope(
            vendorId: (int) $vendor->id,
            userId: $officeUserId,
            role: VendorMemberRole::Staff,
            vendorName: (string) $vendor->name,
            vendorSlug: (string) $vendor->slug,
            agreementAccepted: true,
            paused: $vendor->status === VendorStatus::Paused,
            office: true,
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
            ->whereHas('vendor', fn ($q) => $q->whereIn('status', VendorStatus::portalOpen()))
            ->orderBy('id')
            ->get()
            ->map(fn (VendorMember $m) => [
                'id' => (int) $m->vendor_id,
                'name' => (string) $m->vendor->name,
                'role' => $m->role->value,
                'paused' => $m->vendor->status === VendorStatus::Paused,
            ])->values()->all();
    }
}
