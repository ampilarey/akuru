<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Enums\DeliveryKind;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A vendor's delivery methods (BOOKSHOP_PLAN §5 "Delivery"), replaced as a
 * whole by the owner: which methods, zones and fees, free-over, a minimum
 * order per method, and the carrier-paid flag for boats. `template()`
 * writes the office's starting point (decision 6) for a vendor with none.
 */
class SaveVendorDeliveryMethodsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(VendorScope $scope): array
    {
        return VendorDeliveryMethod::query()->where('vendor_id', $scope->vendorId)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (VendorDeliveryMethod $m) => [
                'id' => $m->id,
                'kind' => $m->kind->value,
                'name' => $m->name,
                'name_dv' => $m->name_dv,
                'name_ar' => $m->name_ar,
                'fee' => (string) $m->fee,
                'free_over' => $m->free_over !== null ? (string) $m->free_over : null,
                'minimum_order' => $m->minimum_order !== null ? (string) $m->minimum_order : null,
                'carrier_paid_on_arrival' => $m->carrier_paid_on_arrival,
                'handling_days' => (int) $m->handling_days,
                'note' => $m->note,
                'is_active' => $m->is_active,
            ])->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function replace(VendorScope $scope, array $rows): void
    {
        if (! $scope->isOwner()) {
            throw new AuthorizationException(__('shop.owner_only'));
        }
        $rows = array_values(array_filter($rows, fn ($r) => trim((string) ($r['name'] ?? '')) !== ''));
        if (count($rows) > 12) {
            throw ValidationException::withMessages(['methods' => __('shop.error_too_many_methods')]);
        }

        DB::transaction(function () use ($scope, $rows) {
            $kept = [];
            foreach ($rows as $index => $row) {
                $kind = DeliveryKind::tryFrom((string) ($row['kind'] ?? ''));
                if ($kind === null) {
                    throw ValidationException::withMessages(['methods' => __('shop.error_delivery_kind')]);
                }
                $method = isset($row['id'])
                    ? VendorDeliveryMethod::query()->where('vendor_id', $scope->vendorId)->find((int) $row['id'])
                    : null;
                $method ??= new VendorDeliveryMethod(['vendor_id' => $scope->vendorId]);
                $method->fill([
                    'kind' => $kind->value,
                    'name' => trim((string) $row['name']),
                    'name_dv' => trim((string) ($row['name_dv'] ?? '')) ?: null,
                    'name_ar' => trim((string) ($row['name_ar'] ?? '')) ?: null,
                    'fee' => $kind === DeliveryKind::Boat ? 0 : (float) ($row['fee'] ?? 0),
                    'free_over' => ($row['free_over'] ?? '') === '' || $row['free_over'] === null ? null : (float) $row['free_over'],
                    'minimum_order' => ($row['minimum_order'] ?? '') === '' || $row['minimum_order'] === null ? null : (float) $row['minimum_order'],
                    'carrier_paid_on_arrival' => $kind === DeliveryKind::Boat || (bool) ($row['carrier_paid_on_arrival'] ?? false),
                    'handling_days' => max(0, (int) ($row['handling_days'] ?? 1)),
                    'note' => trim((string) ($row['note'] ?? '')) ?: null,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'sort_order' => $index,
                ]);
                $method->vendor_id = $scope->vendorId;
                $method->save();
                $kept[] = $method->id;
            }
            VendorDeliveryMethod::query()->where('vendor_id', $scope->vendorId)->whereNotIn('id', $kept)->delete();
        });
    }

    /** The office's template becomes the vendor's own rows, to edit from there. */
    public function template(VendorScope $scope): void
    {
        if (! $scope->isOwner()) {
            throw new AuthorizationException(__('shop.owner_only'));
        }
        if (VendorDeliveryMethod::query()->where('vendor_id', $scope->vendorId)->exists()) {
            return;
        }
        $this->replace($scope, array_map(fn (array $t) => $t + ['is_active' => true], (array) config('bookshop.delivery_template', [])));
    }
}
