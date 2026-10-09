<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\DiscountCode;
use Illuminate\Validation\ValidationException;

/**
 * §35.9 validation + §43.15: a discount reduces the price, nothing else.
 * Limits count pending+confirmed redemptions (abandoned and released ones
 * freed the slot, STATUS §5pq).
 */
class ResolveDiscountAction
{
    /**
     * @return array{discount_code: DiscountCode, amount_discounted: float, final_amount: float}
     */
    public function execute(string $code, int $userId, float $orderAmount, bool $payingWithWallet = false, ?string $scopeType = null, ?int $scopeId = null): array
    {
        $discount = DiscountCode::query()->where('code', strtoupper(trim($code)))->first();
        if ($discount === null || $discount->status !== 'active') {
            throw ValidationException::withMessages(['discount_code' => __('common.error_discount_not_found')]);
        }
        // BOOKSHOP_PLAN B7: a code scoped to one seller (a vendor-funded code)
        // is good only where the caller says it is buying from that seller;
        // callers that pass no scope (the library, courses) accept 'all' codes only.
        $appliesTo = (string) ($discount->applies_to_type ?: 'all');
        if ($appliesTo !== 'all' && ($appliesTo !== $scopeType || (int) $discount->applies_to_id !== (int) $scopeId)) {
            throw ValidationException::withMessages(['discount_code' => __('common.error_discount_not_here')]);
        }
        if ($discount->starts_at !== null && $discount->starts_at->isFuture()) {
            throw ValidationException::withMessages(['discount_code' => __('common.error_discount_not_yet')]);
        }
        if ($discount->ends_at !== null && $discount->ends_at->isPast()) {
            throw ValidationException::withMessages(['discount_code' => __('common.error_discount_ended')]);
        }
        if ($payingWithWallet && ! $discount->can_use_with_wallet) {
            throw ValidationException::withMessages(['discount_code' => __('common.error_discount_no_wallet')]);
        }
        if ($discount->minimum_order_amount !== null && $orderAmount < (float) $discount->minimum_order_amount) {
            throw ValidationException::withMessages(['discount_code' => __('common.error_discount_below_minimum')]);
        }

        $counting = fn ($query) => $query->whereIn('status', ['pending', 'confirmed']);
        if ($discount->usage_limit !== null
            && $counting($discount->redemptions())->count() >= $discount->usage_limit) {
            throw ValidationException::withMessages(['discount_code' => __('common.error_discount_used_up')]);
        }
        if ($discount->per_user_limit !== null
            && $counting($discount->redemptions()->where('user_id', $userId))->count() >= $discount->per_user_limit) {
            throw ValidationException::withMessages(['discount_code' => __('common.error_discount_used_by_you')]);
        }

        $off = $discount->discount_type?->value === 'percentage'
            ? $orderAmount * ((float) $discount->discount_value / 100)
            : (float) $discount->discount_value;
        if ($discount->max_discount_amount !== null) {
            $off = min($off, (float) $discount->max_discount_amount);
        }
        $off = round(min($off, $orderAmount), 2);

        return [
            'discount_code' => $discount,
            'amount_discounted' => $off,
            'final_amount' => round($orderAmount - $off, 2),
        ];
    }
}
