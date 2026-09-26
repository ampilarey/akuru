<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Enums\ProductStatus;
use App\Domains\Bookshop\Enums\VendorMemberRole;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorMember;

/**
 * The office's list of vendors (BOOKSHOP_PLAN §7) with their owners,
 * members, product counts and the commission that actually applies. People
 * are named through the auth model from config, so Bookshop never imports
 * Identity's model (rule 3).
 */
class ListVendorsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $limit = 500): array
    {
        $vendors = Vendor::query()
            ->with('storefront')
            ->withCount(['members', 'products', 'products as active_products_count' => fn ($q) => $q->where('status', ProductStatus::Active->value)])
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $owners = VendorMember::query()
            ->whereIn('vendor_id', $vendors->pluck('id')->all())
            ->where('role', VendorMemberRole::Owner->value)
            ->get()
            ->groupBy('vendor_id');

        $userModel = config('auth.providers.users.model');
        $people = $userModel::query()
            ->whereIn('id', $owners->flatten()->pluck('user_id')->unique()->all())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $default = (float) config('bookshop.default_commission_rate');

        return $vendors->map(function (Vendor $vendor) use ($owners, $people, $default): array {
            $ownerRows = $owners->get($vendor->id, collect());

            return [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'slug' => $vendor->slug,
                'code' => $vendor->code,
                'tagline' => $vendor->tagline,
                'status' => $vendor->status->value,
                'legal_name' => $vendor->legal_name,
                'tin' => $vendor->tin,
                'gst_registered' => $vendor->gst_registered,
                'badges' => (array) ($vendor->badges ?? []),
                'storefront_published_at' => $vendor->storefront?->published_at?->toDateTimeString(),
                'commission_rate' => $vendor->commission_rate !== null ? (string) $vendor->commission_rate : null,
                'effective_commission_rate' => number_format($vendor->commission_rate !== null ? (float) $vendor->commission_rate : $default, 2, '.', ''),
                'contact_email' => $vendor->contact_email,
                'contact_phone' => $vendor->contact_phone,
                'address' => $vendor->address,
                'opening_hours' => $vendor->opening_hours,
                'office_notes' => $vendor->office_notes,
                'owners' => $ownerRows->map(fn (VendorMember $m) => [
                    'name' => $people->get($m->user_id)?->name ?? ('#'.$m->user_id),
                    'email' => $people->get($m->user_id)?->email,
                    'agreement_accepted_at' => $m->agreement_accepted_at?->toDateTimeString(),
                ])->values()->all(),
                'members_count' => (int) $vendor->members_count,
                'products_count' => (int) $vendor->products_count,
                'active_products_count' => (int) $vendor->active_products_count,
                'created_at' => $vendor->created_at?->toDateTimeString(),
            ];
        })->values()->all();
    }
}
