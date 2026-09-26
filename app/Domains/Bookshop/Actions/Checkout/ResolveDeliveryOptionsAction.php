<?php

namespace App\Domains\Bookshop\Actions\Checkout;

use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;

/**
 * How a vendor can get this basket to the customer, with the fee for this
 * basket's sub-total (BOOKSHOP_PLAN §4, decision 6): the vendor's own
 * active methods, or the office's template when the vendor has set none.
 * A method with a minimum above the sub-total is shown but not offered;
 * one with `free_over` at or below it costs nothing; a boat's fee is paid
 * to the carrier and is zero here.
 */
class ResolveDeliveryOptionsAction
{
    /**
     * @return list<array{key: string, kind: string, name: string, fee: string, free: bool, carrier_paid: bool, handling_days: int, minimum_order: ?string, offered: bool, note: ?string}>
     */
    public function execute(Vendor $vendor, float $vendorSubtotal): array
    {
        $methods = $vendor->deliveryMethods()->where('is_active', true)->get();
        $rows = $methods->isNotEmpty()
            ? $methods->map(fn (VendorDeliveryMethod $m) => [
                'key' => 'm'.$m->id,
                'kind' => $m->kind->value,
                'name' => $this->name($m),
                'fee' => (float) $m->fee,
                'free_over' => $m->free_over !== null ? (float) $m->free_over : null,
                'minimum_order' => $m->minimum_order !== null ? (float) $m->minimum_order : null,
                'carrier_paid' => (bool) $m->carrier_paid_on_arrival,
                'handling_days' => (int) $m->handling_days,
                'note' => $m->note,
            ])->values()->all()
            : array_map(fn (array $t, int $i) => [
                'key' => 't'.$i,
                'kind' => (string) $t['kind'],
                'name' => (string) $t['name'],
                'fee' => (float) ($t['fee'] ?? 0),
                'free_over' => isset($t['free_over']) ? (float) $t['free_over'] : null,
                'minimum_order' => isset($t['minimum_order']) ? (float) $t['minimum_order'] : null,
                'carrier_paid' => (bool) ($t['carrier_paid_on_arrival'] ?? false),
                'handling_days' => (int) ($t['handling_days'] ?? 1),
                'note' => $t['note'] ?? null,
            ], (array) config('bookshop.delivery_template', []), array_keys((array) config('bookshop.delivery_template', [])));

        return array_map(function (array $row) use ($vendorSubtotal): array {
            $free = $row['carrier_paid'] || ($row['free_over'] !== null && $vendorSubtotal >= $row['free_over']);
            $fee = $free ? 0.0 : $row['fee'];

            return [
                'key' => $row['key'],
                'kind' => $row['kind'],
                'name' => $row['name'],
                'fee' => number_format($fee, 2, '.', ''),
                'free' => $free && ! $row['carrier_paid'] && $row['fee'] > 0,
                'carrier_paid' => $row['carrier_paid'],
                'handling_days' => $row['handling_days'],
                'minimum_order' => $row['minimum_order'] !== null ? number_format($row['minimum_order'], 2, '.', '') : null,
                'offered' => $row['minimum_order'] === null || $vendorSubtotal >= $row['minimum_order'],
                'note' => $row['note'],
            ];
        }, $rows);
    }

    private function name(VendorDeliveryMethod $m): string
    {
        $locale = app()->getLocale();
        if ($locale === 'dv' && $m->name_dv) {
            return $m->name_dv;
        }
        if ($locale === 'ar' && $m->name_ar) {
            return $m->name_ar;
        }

        return (string) $m->name;
    }
}
