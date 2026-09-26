<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Models\Vendor;

/**
 * The office edits a vendor (BOOKSHOP_PLAN §7): details, commission rate,
 * status (active / suspended), notes. The slug and code never change here —
 * one is the storefront's address, the other is printed on orders.
 */
class UpdateVendorAction
{
    private const EDITABLE = [
        'name', 'tagline', 'legal_name', 'tin', 'gst_registered', 'status', 'commission_rate',
        'contact_email', 'contact_phone', 'address', 'opening_hours', 'office_notes', 'badges',
    ];

    public const BADGES = ['verified', 'akuru_partner'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $vendorId, array $data): Vendor
    {
        $vendor = Vendor::query()->findOrFail($vendorId);
        if (array_key_exists('badges', $data)) {
            // B4 (plan §6.1): only the badges the office knows.
            $data['badges'] = array_values(array_intersect(self::BADGES, array_map('strval', (array) $data['badges'])));
        }
        $vendor->fill(array_intersect_key($data, array_flip(self::EDITABLE)));
        $vendor->save();

        return $vendor;
    }
}
